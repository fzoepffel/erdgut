<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Exports\CatalogueExportData;
use App\Exports\CatalogueMainSheet;
use App\Exports\CataloguePricesSheet;
use App\Exports\CatalogueSheet;
use App\Exports\ProductCatalogueExport;
use App\Imports\ProductCatalogueImport;
use App\Imports\ProductCsvParser;
use App\Models\Producer;
use App\Models\ProducerTranslation;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
use App\Models\ProductSubcategory;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Admin\ProductWriter;
use App\Support\ProductCatalogue\CatalogueSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Product-catalogue CSV export/import round-trip. */
class ProductCatalogueCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 7, 8, 9));
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** A producer whose German display name is $name (so CSV can reference it). */
    private function producer(string $name): Producer
    {
        $producer = Producer::factory()->create();
        ProducerTranslation::query()->updateOrCreate(
            ['producer_id' => $producer->id, 'locale' => 'de'],
            ['name' => $name, 'region' => 'Region', 'description' => 'x'],
        );

        return $producer->fresh();
    }

    private function pieceProduct(Producer $producer, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 2.5,
            'vat_rate_percent' => 7,
        ], $attrs));
    }

    /**
     * Build a CSV string. Each row is keyed by header label; missing cells are
     * blank. Extra headers (e.g. dated availability columns) are appended after
     * the fixed columns.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $extraHeaders
     */
    private function csv(array $rows, array $extraHeaders = []): string
    {
        $headers = array_merge(CatalogueSchema::fixedHeaders(), $extraHeaders);
        $lines = [implode(',', array_map([$this, 'cell'], $headers))];
        foreach ($rows as $row) {
            $line = array_map(fn (string $h): string => $this->cell($row[$h] ?? ''), $headers);
            $lines[] = implode(',', $line);
        }

        return implode("\n", $lines)."\n";
    }

    private function cell(string $value): string
    {
        return str_contains($value, ',') || str_contains($value, '"')
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('katalog.csv', $csv);
    }

    /** A concrete catalogue sheet built over the current data. */
    private function sheet(string $class): CatalogueSheet
    {
        return new $class(app(ProductWriter::class), CatalogueExportData::build());
    }

    // ---------------------------------------------------------------- Export

    public function test_export_emits_one_row_per_producer_main_first(): void
    {
        $primary = $this->producer('Melchhof');
        $secondary = $this->producer('Renner Hof');
        $product = $this->pieceProduct($primary, [
            'metadata' => ['sourcing_id' => 'SRC-9', 'comment' => 'note'],
            'buying_price_cents' => 130,
        ]);
        $product->producers()->attach($secondary->id, ['position' => 1, 'buying_price_cents' => 150]);

        $rows = $this->sheet(CatalogueMainSheet::class)->array();
        $mine = array_values(array_filter($rows, fn ($r): bool => (string) $r[0] === (string) $product->id));

        // Resolve columns by header so the assertions survive a column reorder.
        $col = fn (string $header): int => (int) array_search($header, CatalogueSchema::fixedHeaders(), true);

        $this->assertCount(2, $mine, 'a 2-producer product spans 2 rows');
        // Main row first, full fields.
        $this->assertSame('Main', $mine[0][$col('Main')]);
        $this->assertSame('Melchhof', $mine[0][$col('Producer')]);
        $this->assertSame('Obst & Gemüse', $mine[0][$col('Category')]);
        $this->assertSame('SRC-9', $mine[0][$col('Sourcing ID')]);
        $this->assertSame('note', $mine[0][$col('Comment')]);
        $this->assertSame('1.30', $mine[0][$col('Cost')]); // primary buying price
        // Secondary row: product-level cells blank, only Producer + Cost.
        $this->assertSame('', $mine[1][$col('Main')]);
        $this->assertSame('Renner Hof', $mine[1][$col('Producer')]);
        $this->assertSame('', $mine[1][$col('Category')]); // no category on secondary
        $this->assertSame('1.50', $mine[1][$col('Cost')]);
    }

    public function test_export_route_downloads_xlsx(): void
    {
        $this->pieceProduct($this->producer('Melchhof'));

        $this->actingAs($this->admin())
            ->get(route('app.admin.products.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=erdgut-katalog-2026-07-08.xlsx');
    }

    public function test_export_reimport_xlsx_round_trips_and_preserves_line_breaks(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, ['unit_price' => 3.5]);
        // A long description with a hard line break must survive the XLSX round-trip.
        $product->translationFor('de')->update(['taste_profile' => "Zeile eins.\nZeile zwei."]);

        $xlsx = Excel::raw(app(ProductCatalogueExport::class), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
        file_put_contents($path, $xlsx);
        $result = app(ProductCsvParser::class)->parse($path);
        @unlink($path);

        // Matching the export cell-for-cell (including the hard line break) means
        // the product is a no-op — the round-trip preserved every field.
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['summary']['to_create']);
        $this->assertSame(0, $result['summary']['to_update']);
        $this->assertSame(1, $result['summary']['unchanged']);
    }

    // ------------------------------------------------------------ Round-trip

    public function test_export_then_reimport_is_idempotent(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, [
            'metadata' => ['sourcing_id' => 'SRC-1'],
            'unit_price' => 3.5,
        ]);
        ProductPriceRule::query()->create([
            'product_id' => $product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 2.9,
            'is_active' => true,
        ]);

        $csv = Excel::raw(app(ProductCatalogueExport::class), \Maatwebsite\Excel\Excel::CSV);
        $path = tempnam(sys_get_temp_dir(), 'cat').'.csv';
        file_put_contents($path, $csv);
        $result = app(ProductCsvParser::class)->parse($path);
        @unlink($path);

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['summary']['to_create']);
        $this->assertSame(0, $result['summary']['to_update']);
        $this->assertSame(1, $result['summary']['unchanged']);
    }

    // -------------------------------------------------------- Preview (dry)

    public function test_preview_summarizes_and_writes_nothing(): void
    {
        $producer = $this->producer('Melchhof');
        $existing = $this->pieceProduct($producer, ['unit_price' => 2.0]);
        $productCountBefore = Product::query()->count();

        $csv = $this->csv([
            // update existing: change price
            $this->row($existing->id, 'Melchhof', 'Bestehend', unit: 'St', price: '9.99'),
            // create new, with a brand-new group
            $this->row('', 'Melchhof', 'Neues Produkt', unit: 'St', price: '4.00', group: 'Frische Kräuter'),
            // bad row: unknown producer → skipped
            $this->row('', 'Geisterhof', 'Kaputt', unit: 'St', price: '1.00'),
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)]);

        $response->assertOk()->assertViewIs('admin.products.import-preview');
        $result = $response->viewData('result');

        $this->assertSame(1, $result['summary']['to_create']);
        $this->assertSame(1, $result['summary']['to_update']);
        $this->assertSame(['Frische Kräuter'], $result['summary']['new_groups']);
        $this->assertSame(1, $result['summary']['errors']);
        $this->assertStringContainsString('Geisterhof', $result['errors'][0]['message']);

        // Nothing written yet.
        $this->assertSame($productCountBefore, Product::query()->count());
        $this->assertSame(2.0, (float) $existing->fresh()->unit_price);
        $this->assertSame(0, ProductGroup::query()->count());
    }

    // -------------------------------------------------------- Confirm (write)

    public function test_confirm_applies_creates_updates_and_groups(): void
    {
        $producer = $this->producer('Melchhof');
        $existing = $this->pieceProduct($producer, ['unit_price' => 2.0]);

        $csv = $this->csv([
            $this->row($existing->id, 'Melchhof', 'Bestehend', unit: 'St', price: '9.99'),
            $this->row('', 'Melchhof', 'Neues Produkt', unit: 'St', price: '4.00', group: 'Frische Kräuter'),
            $this->row('', 'Geisterhof', 'Kaputt', unit: 'St', price: '1.00'),
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)])
            ->assertOk();

        // Grab the token the preview stashed.
        $token = collect(Storage::disk('local')->files('catalogue-imports'))
            ->map(fn ($p): string => basename($p, '.csv'))->first();
        $this->assertNotNull($token);

        $this->actingAs($admin)
            ->post(route('app.admin.products.import.confirm'), [
                'token' => $token,
                // The brand-new group needs its three locale names (the dialog).
                'group_translations' => [
                    ['name' => 'Frische Kräuter', 'de' => 'Frische Kräuter', 'en' => 'Fresh herbs', 'fr' => 'Herbes fraîches'],
                ],
            ])
            ->assertRedirect(route('app.admin.products.index'));

        // Existing updated.
        $this->assertSame(9.99, (float) $existing->fresh()->unit_price);
        // New created with its group, all three locale names stored.
        $created = Product::query()->whereHas('translations', fn ($q) => $q->where('locale', 'de')->where('name', 'Neues Produkt'))->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->group_id);
        $this->assertSame('Frische Kräuter', $created->group->localizedName('de'));
        $this->assertSame('Fresh herbs', $created->group->localizedName('en'));
        // Bad row skipped: no "Kaputt" product.
        $this->assertNull(Product::query()->whereHas('translations', fn ($q) => $q->where('name', 'Kaputt'))->first());
        // Stash cleaned up.
        $this->assertEmpty(Storage::disk('local')->files('catalogue-imports'));
    }

    // ------------------------------------------------------- Subcategory sections

    public function test_export_groups_products_into_subcategory_sections(): void
    {
        $producer = $this->producer('Melchhof');
        $sub = ProductSubcategory::create([
            'category' => 'produce',
            'label_de' => 'Wurzelgemüse', 'label_en' => 'Roots', 'label_fr' => 'Racines',
            'sort_order' => 0,
        ]);

        $this->pieceProduct($producer, ['subcategory_id' => $sub->id])->translationFor('de')->update(['name' => 'Zwiebel']);
        $this->pieceProduct($producer, ['subcategory_id' => $sub->id])->translationFor('de')->update(['name' => 'Karotte']);
        $this->pieceProduct($producer)->translationFor('de')->update(['name' => 'Apfel']);

        $rows = $this->sheet(CatalogueMainSheet::class)->array();
        // Compact sequence: section title rows (blank name) vs product rows.
        $seq = array_map(
            static fn ($r): string => (string) $r[1] !== '' ? 'P:'.$r[1] : 'S:'.$r[0],
            $rows,
        );

        // Named subcategory first (products alphabetical by German name), then
        // the "Ohne Unterkategorie" section, and finally the add-new-products
        // footer (a section band + a guidance note).
        $this->assertSame(
            [
                'S:Wurzelgemüse', 'P:Karotte', 'P:Zwiebel',
                'S:Ohne Unterkategorie', 'P:Apfel',
                'S:Neue Produkte unten hinzufügen',
                'S:Die Erdgut ID leer lassen, sie wird beim Import automatisch vergeben. Alle Eingaben werden nach dem Upload geprüft.',
            ],
            $seq,
        );
    }

    public function test_import_skips_section_title_rows(): void
    {
        $this->producer('Melchhof');

        $csv = $this->csv([
            ['Erdgut ID' => 'Wurzelgemüse'], // section-title divider row
            $this->row('', 'Melchhof', 'Karotte', unit: 'St', price: '2.00'),
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)]);

        $response->assertOk();
        $result = $response->viewData('result');
        $this->assertSame(1, $result['summary']['to_create']);
        $this->assertSame(0, $result['summary']['errors'], 'the divider row is skipped, not an error');
    }

    public function test_new_subcategory_is_created_from_dialog_and_assigned(): void
    {
        $producer = $this->producer('Melchhof');

        $csv = $this->csv([
            $this->row('', 'Melchhof', 'Karotte', unit: 'St', price: '2.00', extra: ['Sub-category' => 'Wurzelgemüse']),
        ]);

        $admin = $this->admin();
        $response = $this->actingAs($admin)
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)]);
        $response->assertOk();
        $this->assertSame(['Wurzelgemüse'], $response->viewData('result')['summary']['new_subcategories']);

        $token = collect(Storage::disk('local')->files('catalogue-imports'))
            ->map(fn ($p): string => basename($p, '.csv'))->first();

        $this->actingAs($admin)
            ->post(route('app.admin.products.import.confirm'), [
                'token' => $token,
                'subcategory_translations' => [
                    ['name' => 'Wurzelgemüse', 'de' => 'Wurzelgemüse', 'en' => 'Roots', 'fr' => 'Racines'],
                ],
            ])
            ->assertRedirect(route('app.admin.products.index'));

        $sub = ProductSubcategory::query()->where('label_de', 'Wurzelgemüse')->first();
        $this->assertNotNull($sub);
        $this->assertSame('produce', $sub->category);
        $this->assertSame('Roots', $sub->label_en);
        $this->assertSame('Racines', $sub->label_fr);

        $created = Product::query()->whereHas('translations', fn ($q) => $q->where('name', 'Karotte'))->first();
        $this->assertSame($sub->id, (int) $created->subcategory_id);
    }

    public function test_confirm_is_blocked_until_new_translations_are_filled(): void
    {
        $producer = $this->producer('Melchhof');

        $csv = $this->csv([
            $this->row('', 'Melchhof', 'Karotte', unit: 'St', price: '2.00', extra: ['Sub-category' => 'Wurzelgemüse']),
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)])
            ->assertOk();
        $token = collect(Storage::disk('local')->files('catalogue-imports'))
            ->map(fn ($p): string => basename($p, '.csv'))->first();

        // Confirm without the dialog translations: re-renders the preview, no write.
        $response = $this->actingAs($admin)
            ->post(route('app.admin.products.import.confirm'), ['token' => $token]);
        $response->assertOk()->assertViewIs('admin.products.import-preview');
        $this->assertNotNull($response->viewData('translationError'));

        $this->assertSame(0, ProductSubcategory::query()->count());
        $this->assertNull(Product::query()->whereHas('translations', fn ($q) => $q->where('name', 'Karotte'))->first());
        // Stash kept so the admin can complete the dialog and retry.
        $this->assertNotEmpty(Storage::disk('local')->files('catalogue-imports'));
    }

    public function test_existing_subcategory_is_matched_without_dialog(): void
    {
        $producer = $this->producer('Melchhof');
        $sub = ProductSubcategory::create([
            'category' => 'produce',
            'label_de' => 'Wurzelgemüse', 'label_en' => 'Roots', 'label_fr' => 'Racines',
            'sort_order' => 0,
        ]);

        $csv = $this->csv([
            $this->row('', 'Melchhof', 'Karotte', unit: 'St', price: '2.00', extra: ['Sub-category' => 'Wurzelgemüse']),
        ]);

        $admin = $this->admin();
        $response = $this->actingAs($admin)
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)]);
        // Existing subcategory: nothing to fill in the dialog.
        $this->assertSame([], $response->viewData('result')['summary']['new_subcategories']);

        $token = collect(Storage::disk('local')->files('catalogue-imports'))
            ->map(fn ($p): string => basename($p, '.csv'))->first();
        $this->actingAs($admin)
            ->post(route('app.admin.products.import.confirm'), ['token' => $token])
            ->assertRedirect(route('app.admin.products.index'));

        $created = Product::query()->whereHas('translations', fn ($q) => $q->where('name', 'Karotte'))->first();
        $this->assertSame($sub->id, (int) $created->subcategory_id);
        $this->assertSame(1, ProductSubcategory::query()->count(), 'no duplicate subcategory created');
    }

    // ------------------------------------------------------------- Module B

    public function test_day_column_writes_csv_owned_inflow_and_leaves_schedules(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, ['default_freshness_window_days' => 7]);

        // A non-Monday date inside the export window (the old weekly export
        // could never see this; the daily columns can).
        $date = Carbon::now()->addDays(3);
        $dayHeader = CatalogueSchema::dayHeader($date);

        // A schedule-materialized batch on the same day must stay untouched.
        $schedule = ProductStockInflowSchedule::query()->create([
            'product_id' => $product->id,
            'producer_id' => $producer->id,
            'weekday' => (int) $date->dayOfWeekIso,
            'planned_quantity' => 12,
            'freshness_window_days' => 7,
            'starts_on' => $date->toDateString(),
        ]);
        $scheduled = ProductStockInflow::query()->create([
            'product_id' => $product->id,
            'producer_id' => $producer->id,
            'received_on' => $date->toDateString(),
            'original_received_on' => $date->toDateString(),
            'quantity' => 12,
            'freshness_window_days' => 7,
            'source_schedule_id' => $schedule->id,
        ]);

        $csv = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: ['Freshness (days)' => '7', $dayHeader => '5'])],
            [$dayHeader],
        );
        $this->applyCsv($csv);

        $csvInflow = ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->where('notes', ProductCatalogueImport::CSV_NOTE)
            ->whereDate('received_on', $date->toDateString())
            ->first();
        $this->assertNotNull($csvInflow);
        $this->assertSame('5.0000', (string) $csvInflow->quantity);
        $this->assertSame(7, (int) $csvInflow->freshness_window_days);
        $this->assertNull($csvInflow->source_schedule_id);

        // Untouched schedule batch.
        $this->assertSame('12.0000', (string) $scheduled->fresh()->quantity);
        $this->assertSame($schedule->id, (int) $scheduled->fresh()->source_schedule_id);

        // Re-import "Unlimited" updates the same CSV inflow to the sentinel.
        $csv2 = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: ['Freshness (days)' => '7', $dayHeader => 'Unlimited'])],
            [$dayHeader],
        );
        $this->applyCsv($csv2);
        $this->assertSame(1, ProductStockInflow::query()->where('notes', ProductCatalogueImport::CSV_NOTE)->count());
        $this->assertSame(
            (float) CatalogueSchema::UNLIMITED_SENTINEL,
            (float) $csvInflow->fresh()->quantity,
        );

        // "0" zeroes it.
        $csv3 = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: ['Freshness (days)' => '7', $dayHeader => '0'])],
            [$dayHeader],
        );
        $this->applyCsv($csv3);
        $this->assertSame(0.0, (float) $csvInflow->fresh()->quantity);
    }

    public function test_day_column_replaces_existing_manual_inflows_for_that_day(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, ['default_freshness_window_days' => 3]);

        $date = Carbon::now()->addDays(2);
        $dayHeader = CatalogueSchema::dayHeader($date);

        // Two pre-existing one-off inflows on the day, entered outside the CSV
        // (notes null). The day column value is authoritative, so both are
        // consolidated into one CSV-owned row = the entered number.
        foreach ([4, 6] as $qty) {
            ProductStockInflow::query()->create([
                'product_id' => $product->id,
                'producer_id' => $producer->id,
                'received_on' => $date->toDateString(),
                'original_received_on' => $date->toDateString(),
                'quantity' => $qty,
                'freshness_window_days' => 3,
                'source_schedule_id' => null,
            ]);
        }

        $csv = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: [$dayHeader => '9'])],
            [$dayHeader],
        );
        $this->applyCsv($csv);

        $oneOffs = ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->whereNull('source_schedule_id')
            ->whereDate('received_on', $date->toDateString())
            ->get();
        $this->assertCount(1, $oneOffs, 'two manual inflows consolidated into one');
        $this->assertSame(9.0, (float) $oneOffs->first()->quantity);
        $this->assertSame(ProductCatalogueImport::CSV_NOTE, $oneOffs->first()->notes);
    }

    public function test_export_shows_single_inflow_on_any_weekday(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer);

        // A Friday inflow the old Monday-only export could never surface.
        $date = Carbon::now()->next(Carbon::FRIDAY);
        ProductStockInflow::query()->create([
            'product_id' => $product->id,
            'producer_id' => $producer->id,
            'received_on' => $date->toDateString(),
            'original_received_on' => $date->toDateString(),
            'quantity' => 7,
            'freshness_window_days' => 1,
            'source_schedule_id' => null,
        ]);

        $headings = $this->sheet(CatalogueMainSheet::class)->headings();
        $col = array_search(CatalogueSchema::dayHeader($date), $headings, true);
        $this->assertNotFalse($col, 'the Friday date has its own column');

        $rows = $this->sheet(CatalogueMainSheet::class)->array();
        $mine = array_values(array_filter($rows, fn ($r): bool => (string) $r[0] === (string) $product->id));
        $this->assertSame('7', $mine[0][$col]);
    }

    public function test_day_column_converts_kg_display_to_canonical_grams(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, [
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.009, // 9 €/kg canonical
        ]);
        $date = Carbon::now()->addDays(5);
        $dayHeader = CatalogueSchema::dayHeader($date);

        // 3 kg planned → 3000 g canonical.
        $csv = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'kg', price: '9.00', extra: [$dayHeader => '3'])],
            [$dayHeader],
        );
        $this->applyCsv($csv);

        $inflow = ProductStockInflow::query()->where('notes', ProductCatalogueImport::CSV_NOTE)->first();
        $this->assertNotNull($inflow);
        $this->assertSame(3000.0, (float) $inflow->quantity);
    }

    public function test_secondary_producer_day_column_records_per_producer_inflow(): void
    {
        $primary = $this->producer('Melchhof');
        $secondary = $this->producer('Renner Hof');
        $product = $this->pieceProduct($primary);
        $product->producers()->attach($secondary->id, ['position' => 1, 'buying_price_cents' => 150]);

        $date = Carbon::now()->addDays(3);
        $dayHeader = CatalogueSchema::dayHeader($date);

        // Main row stocks the primary farm; the secondary producer's own row
        // stocks that farm. Both must land as separate per-producer inflows.
        $csv = $this->csv([
            $this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: [$dayHeader => '5']),
            ['Erdgut ID' => (string) $product->id, 'Producer' => 'Renner Hof', $dayHeader => '3'],
        ], [$dayHeader]);
        $this->applyCsv($csv);

        $primaryInflow = ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->where('producer_id', $primary->id)
            ->whereDate('received_on', $date->toDateString())
            ->first();
        $secondaryInflow = ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->where('producer_id', $secondary->id)
            ->whereDate('received_on', $date->toDateString())
            ->first();

        $this->assertNotNull($primaryInflow, 'primary producer stock recorded');
        $this->assertNotNull($secondaryInflow, 'secondary producer stock recorded');
        $this->assertSame(5.0, (float) $primaryInflow->quantity);
        $this->assertSame(3.0, (float) $secondaryInflow->quantity);
        $this->assertSame(ProductCatalogueImport::CSV_NOTE, $secondaryInflow->notes);

        // The product's availability for the day is the sum of both farms.
        $this->assertSame(
            8.0,
            (float) app(InventoryService::class)->availableOnDate($product->fresh(), $date),
        );
    }

    public function test_export_shows_each_producer_stock_and_reimports_clean(): void
    {
        $primary = $this->producer('Melchhof');
        $secondary = $this->producer('Renner Hof');
        $product = $this->pieceProduct($primary);
        $product->producers()->attach($secondary->id, ['position' => 1, 'buying_price_cents' => 150]);

        $date = Carbon::now()->addDays(2);
        foreach ([[$primary->id, 5], [$secondary->id, 3]] as [$producerId, $qty]) {
            ProductStockInflow::query()->create([
                'product_id' => $product->id,
                'producer_id' => $producerId,
                'received_on' => $date->toDateString(),
                'original_received_on' => $date->toDateString(),
                'quantity' => $qty,
                'freshness_window_days' => 1,
                'source_schedule_id' => null,
            ]);
        }

        // Export: the Main row shows the primary farm's 5, the secondary row its 3.
        $rows = $this->sheet(CatalogueMainSheet::class)->array();
        $mine = array_values(array_filter($rows, fn ($r): bool => (string) $r[0] === (string) $product->id));
        $headings = $this->sheet(CatalogueMainSheet::class)->headings();
        $dayCol = array_search(CatalogueSchema::dayHeader($date), $headings, true);
        $this->assertCount(2, $mine);
        $this->assertSame('5', $mine[0][$dayCol], 'Main row shows primary farm stock');
        $this->assertSame('3', $mine[1][$dayCol], 'secondary row shows its own farm stock');

        // A clean round-trip changes nothing (per-producer values match the DB).
        $result = $this->reimport(fn ($book) => null);
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['summary']['to_update']);
        $this->assertSame(1, $result['summary']['unchanged']);
        $this->assertSame(0, $result['summary']['weekly_changes']);
    }

    public function test_editing_only_secondary_producer_stock_on_reimport_updates_that_farm(): void
    {
        $primary = $this->producer('Melchhof');
        $secondary = $this->producer('Renner Hof');
        $product = $this->pieceProduct($primary);
        $product->producers()->attach($secondary->id, ['position' => 1, 'buying_price_cents' => 150]);

        $date = Carbon::now()->addDays(2);
        foreach ([[$primary->id, 5], [$secondary->id, 3]] as [$producerId, $qty]) {
            ProductStockInflow::query()->create([
                'product_id' => $product->id,
                'producer_id' => $producerId,
                'received_on' => $date->toDateString(),
                'original_received_on' => $date->toDateString(),
                'quantity' => $qty,
                'freshness_window_days' => 1,
                'source_schedule_id' => null,
            ]);
        }

        // Bump only the secondary producer's row on the "Katalog" sheet.
        $result = $this->reimport(function ($book) use ($product, $secondary, $date): void {
            $this->setProducerCell($book, $product->id, $secondary->id, CatalogueSchema::dayHeader($date), '9');
        });

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['summary']['to_update']);
        $intent = collect($result['products'][0]['weekly'])->firstWhere('producer_id', $secondary->id);
        $this->assertNotNull($intent, 'secondary producer edit surfaced as an intent');
        $this->assertSame(9.0, $intent['canonical']);
        $this->assertSame($date->toDateString(), $intent['date']);
    }

    public function test_overlong_short_description_is_a_row_error_not_a_crash(): void
    {
        $producer = $this->producer('Melchhof');

        // 241 characters: one over the short_description column limit. MySQL
        // rejects this on write; the parser must catch it up front as a clean
        // row error so the import preview stays usable instead of 500-ing.
        $tooLong = str_repeat('a', 241);

        $csv = $this->csv([
            $this->row('', 'Melchhof', 'Neu', unit: 'St', price: '2.00', extra: ['Short Description (DE)' => $tooLong]),
            $this->row('', 'Melchhof', 'Sauber', unit: 'St', price: '3.00'),
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)]);
        $response->assertOk();
        $result = $response->viewData('result');

        // The bad row is reported, naming the column; the clean row still imports.
        $this->assertSame(1, $result['summary']['errors']);
        $this->assertStringContainsString('Short Description (DE)', $result['errors'][0]['message']);
        $this->assertStringContainsString('241/240', $result['errors'][0]['message']);
        $this->assertSame(1, $result['summary']['to_create']);
    }

    public function test_short_description_at_the_limit_is_accepted(): void
    {
        $producer = $this->producer('Melchhof');
        $exactly = str_repeat('a', 240);

        $csv = $this->csv([
            $this->row('', 'Melchhof', 'Neu', unit: 'St', price: '2.00', extra: ['Short Description (DE)' => $exactly]),
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)]);
        $response->assertOk();
        $result = $response->viewData('result');

        $this->assertSame(0, $result['summary']['errors']);
        $this->assertSame(1, $result['summary']['to_create']);
    }

    // ----------------------------------------------------------- ProductWriter

    public function test_writer_upsert_creates_business_rule_and_group(): void
    {
        $producer = $this->producer('Melchhof');

        $product = app(ProductWriter::class)->upsert([
            'unit' => 'piece',
            'price_display_unit' => 'piece',
            'price' => 5.0,
            'producer_ids' => [$producer->id],
            'producers' => [['producer_id' => $producer->id, 'buying_price' => 1.25, 'main' => true]],
            'category' => 'produce',
            'vat_rate_percent' => 7,
            'business_price' => 4.0,
            'group_name' => ['de' => 'Neue Gruppe'],
            'translations' => ['de' => ['name' => 'Test', 'origin' => '', 'variety' => '', 'taste_profile' => '', 'short_description' => '']],
            'buying_price' => 1.25,
        ]);

        $this->assertSame(125, (int) $product->buying_price_cents);
        $this->assertNotNull($product->group_id);
        $rule = $product->priceRules()->where('customer_type', CustomerType::Business->value)->first();
        $this->assertNotNull($rule);
        $this->assertSame(4.0, (float) $rule->unit_price); // piece factor 1
    }

    // -------------------------------------------------- Prices/Bestand sheets

    public function test_export_workbook_has_three_sheets(): void
    {
        $this->pieceProduct($this->producer('Melchhof'));

        $sheets = app(ProductCatalogueExport::class)->sheets();
        $titles = array_map(fn ($s): string => $s->title(), $sheets);

        $this->assertSame(['Katalog', 'Preise', 'Bestand'], $titles);
        $this->assertSame(CatalogueSchema::pricesHeaders(), $sheets[1]->headings());
        // Inventory sheet: fixed columns then one column per upcoming day.
        $this->assertSame(CatalogueSchema::inventoryFixedHeaders(), array_slice($sheets[2]->headings(), 0, 3));
        $this->assertContains(CatalogueSchema::dayHeader(Carbon::now()->addDay()), $sheets[2]->headings());
    }

    public function test_main_sheet_has_add_new_products_footer(): void
    {
        $this->pieceProduct($this->producer('Melchhof'));

        $mainA = array_map(fn (array $r): string => (string) ($r[0] ?? ''), $this->sheet(CatalogueMainSheet::class)->array());
        $this->assertContains('Neue Produkte unten hinzufügen', $mainA);
        $this->assertNotEmpty(array_filter($mainA, fn (string $c): bool => str_contains($c, 'Die Erdgut ID leer lassen')));

        // The footer only belongs on the full sheet, where products are created.
        $pricesA = array_map(fn (array $r): string => (string) ($r[0] ?? ''), $this->sheet(CataloguePricesSheet::class)->array());
        $this->assertNotContains('Neue Produkte unten hinzufügen', $pricesA);
    }

    public function test_footer_rows_do_not_create_phantom_products_on_reimport(): void
    {
        $this->pieceProduct($this->producer('Melchhof'));

        // A clean round-trip: the guidance rows must be skipped, not imported,
        // and the untouched product must not appear as an update.
        $result = $this->reimport(fn ($book) => null);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['products']);
        $this->assertSame(1, $result['summary']['unchanged']);
        $this->assertSame(0, $result['summary']['to_update']);
    }

    public function test_clean_reimport_reports_only_real_changes(): void
    {
        $product = $this->pieceProduct($this->producer('Melchhof'), ['unit_price' => 2.5]);

        // Untouched file → nothing to apply.
        $clean = $this->reimport(fn ($book) => null);
        $this->assertSame(0, $clean['summary']['to_update']);
        $this->assertSame(1, $clean['summary']['unchanged']);
        $this->assertSame(0, $clean['summary']['weekly_changes']);

        // A real edit still surfaces as an update with the changed field named.
        $edited = $this->reimport(function ($book) use ($product): void {
            $this->setCell($book, CatalogueSchema::SHEET_MAIN, 'Private Price', $product->id, '9.90');
        });
        $this->assertSame(1, $edited['summary']['to_update']);
        $this->assertSame(0, $edited['summary']['unchanged']);
        $this->assertContains('Private Price', $edited['products'][0]['changed_fields']);
    }

    public function test_crlf_long_descriptions_do_not_count_as_changes(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, ['unit_price' => 2.5]);
        // Windows-style line endings, as often stored from admin forms / paste.
        foreach (['de', 'en', 'fr'] as $locale) {
            $product->translationFor($locale)->update([
                'taste_profile' => "Zeile eins.\r\nZeile zwei.",
            ]);
        }

        // Clean re-upload: Excel turns CRLF into LF, but that must not look like an edit.
        $clean = $this->reimport(fn ($book) => null);
        $this->assertSame(0, $clean['summary']['to_update']);
        $this->assertSame(1, $clean['summary']['unchanged']);

        $sub = ProductSubcategory::create([
            'category' => 'produce',
            'label_de' => 'Gemüse', 'label_en' => 'Vegetables', 'label_fr' => 'Légumes',
            'sort_order' => 0,
        ]);

        // Subcategory-only edit must not rewrite the CRLF descriptions on apply.
        $edited = $this->reimport(function ($book) use ($product): void {
            $this->setCell($book, CatalogueSchema::SHEET_MAIN, 'Sub-category', $product->id, 'Gemüse');
        });
        $this->assertSame(1, $edited['summary']['to_update']);
        $this->assertSame(['Sub-category'], $edited['products'][0]['changed_fields']);
        $this->assertSame("Zeile eins.\r\nZeile zwei.", $edited['products'][0]['data']['translations']['de']['taste_profile']);
        $this->assertSame("Zeile eins.\r\nZeile zwei.", $edited['products'][0]['data']['translations']['en']['taste_profile']);
        $this->assertSame("Zeile eins.\r\nZeile zwei.", $edited['products'][0]['data']['translations']['fr']['taste_profile']);
        $this->assertSame($sub->id, $edited['products'][0]['data']['subcategory_id']);
    }

    public function test_price_edited_on_prices_sheet_updates_product(): void
    {
        $product = $this->pieceProduct($this->producer('Melchhof'), ['unit_price' => 2.5]);

        // Change the price only on the "Preise" sheet; leave "Katalog" stale.
        $result = $this->reimport(function ($book) use ($product): void {
            $this->setCell($book, CatalogueSchema::SHEET_PRICES, 'Private Price', $product->id, '9.90');
        });

        $this->assertSame([], $result['errors']);
        $this->assertSame(9.9, $result['products'][0]['data']['price']);
    }

    public function test_price_edited_on_main_sheet_wins_when_prices_sheet_stale(): void
    {
        $product = $this->pieceProduct($this->producer('Melchhof'), ['unit_price' => 2.5]);

        // The reverse: edit only "Katalog"; the stale "Preise" value must not win.
        $result = $this->reimport(function ($book) use ($product): void {
            $this->setCell($book, CatalogueSchema::SHEET_MAIN, 'Private Price', $product->id, '4.40');
        });

        $this->assertSame([], $result['errors']);
        $this->assertSame(4.4, $result['products'][0]['data']['price']);
    }

    public function test_price_conflict_prefers_prices_sheet_and_warns(): void
    {
        $product = $this->pieceProduct($this->producer('Melchhof'), ['unit_price' => 2.5]);

        // Both sheets moved off the stored 2.50, to different values.
        $result = $this->reimport(function ($book) use ($product): void {
            $this->setCell($book, CatalogueSchema::SHEET_MAIN, 'Private Price', $product->id, '8.00');
            $this->setCell($book, CatalogueSchema::SHEET_PRICES, 'Private Price', $product->id, '9.90');
        });

        $this->assertSame(9.9, $result['products'][0]['data']['price']);
        $this->assertNotEmpty(array_filter(
            $result['products'][0]['warnings'],
            fn (string $w): bool => str_contains($w, 'Private Price'),
        ));
    }

    public function test_stock_edited_on_inventory_sheet_updates_product(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer);
        $date = Carbon::now()->addDays(3);
        ProductStockInflow::query()->create([
            'product_id' => $product->id,
            'producer_id' => $producer->id,
            'received_on' => $date->toDateString(),
            'original_received_on' => $date->toDateString(),
            'quantity' => 5,
            'freshness_window_days' => 1,
            'source_schedule_id' => null,
        ]);

        // Bump that day only on the "Bestand" sheet; "Katalog" stays at 5.
        $result = $this->reimport(function ($book) use ($product, $date): void {
            $this->setCell($book, CatalogueSchema::SHEET_INVENTORY, CatalogueSchema::dayHeader($date), $product->id, '12');
        });

        $this->assertSame([], $result['errors']);
        $intent = collect($result['products'][0]['weekly'])->firstWhere('date', $date->toDateString());
        $this->assertNotNull($intent);
        $this->assertSame(12.0, $intent['canonical']);
    }

    // ------------------------------------------------------------- helpers

    /**
     * Export the real workbook, let $edit mutate the loaded spreadsheet, then
     * parse the saved file back through the importer. Exercises the full
     * multi-sheet read + reconciliation path.
     *
     * @return array<string, mixed>
     */
    private function reimport(callable $edit): array
    {
        $xlsx = Excel::raw(app(ProductCatalogueExport::class), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
        file_put_contents($path, $xlsx);

        $book = IOFactory::load($path);
        $edit($book);
        (new Xlsx($book))->save($path);

        $result = app(ProductCsvParser::class)->parse($path);
        @unlink($path);

        return $result;
    }

    /** Set the cell on $sheetTitle where the header is $header and Erdgut ID is $id. */
    private function setCell(Spreadsheet $book, string $sheetTitle, string $header, int $id, string $value): void
    {
        $sheet = $book->getSheetByName($sheetTitle);
        $this->assertNotNull($sheet, "sheet {$sheetTitle} exists");

        $matrix = $sheet->toArray(null, false, false, false);
        $headers = array_map(fn ($h): string => trim((string) $h), $matrix[0] ?? []);
        $col = array_search($header, $headers, true);
        $idCol = array_search('Erdgut ID', $headers, true);
        $this->assertNotFalse($col, "header {$header} on {$sheetTitle}");

        $rowNumber = null;
        foreach ($matrix as $i => $cells) {
            if ($i > 0 && (string) ($cells[$idCol] ?? '') === (string) $id) {
                $rowNumber = $i + 1;
                break;
            }
        }
        $this->assertNotNull($rowNumber, "row for id {$id} on {$sheetTitle}");

        $sheet->setCellValue([$col + 1, $rowNumber], $value);
    }

    /**
     * Set a cell on the "Katalog" sheet row that belongs to a specific
     * producer of a multi-producer product (matched by Erdgut ID + the
     * producer's German name), so secondary-row edits can be targeted.
     */
    private function setProducerCell(Spreadsheet $book, int $productId, int $producerId, string $header, string $value): void
    {
        $producerName = Producer::query()->findOrFail($producerId)->translationFor('de')?->name;
        $sheet = $book->getSheetByName(CatalogueSchema::SHEET_MAIN);
        $this->assertNotNull($sheet);

        $matrix = $sheet->toArray(null, false, false, false);
        $headers = array_map(fn ($h): string => trim((string) $h), $matrix[0] ?? []);
        $col = array_search($header, $headers, true);
        $idCol = array_search('Erdgut ID', $headers, true);
        $producerCol = array_search('Producer', $headers, true);
        $this->assertNotFalse($col, "header {$header}");

        $rowNumber = null;
        foreach ($matrix as $i => $cells) {
            if ($i > 0
                && (string) ($cells[$idCol] ?? '') === (string) $productId
                && (string) ($cells[$producerCol] ?? '') === (string) $producerName) {
                $rowNumber = $i + 1;
                break;
            }
        }
        $this->assertNotNull($rowNumber, "row for producer {$producerName} of product {$productId}");

        $sheet->setCellValue([$col + 1, $rowNumber], $value);
    }

    /**
     * One CSV row keyed by header. Only the columns a test cares about are
     * set; the rest stay blank.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function row(
        int|string $id,
        string $producer,
        string $nameDe,
        string $unit,
        string $price,
        string $group = '',
        array $extra = [],
    ): array {
        return array_merge([
            'Erdgut ID' => (string) $id,
            'Main' => 'Main',
            'Producer' => $producer,
            'Category' => 'Obst & Gemüse',
            'Product Group' => $group,
            'Name (DE)' => $nameDe,
            'Unit' => $unit,
            'Private Price' => $price,
            'VAT %' => '7',
            'Published' => '1',
            'Available' => '1',
        ], $extra);
    }

    private function applyCsv(string $csv): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('app.admin.products.import'), ['file' => $this->upload($csv)])
            ->assertOk();
        $token = collect(Storage::disk('local')->files('catalogue-imports'))
            ->map(fn ($p): string => basename($p, '.csv'))->first();
        $this->actingAs($admin)
            ->post(route('app.admin.products.import.confirm'), ['token' => $token])
            ->assertRedirect(route('app.admin.products.index'));
    }
}
