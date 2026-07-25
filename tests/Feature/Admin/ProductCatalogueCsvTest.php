<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
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
use App\Models\User;
use App\Services\Admin\ProductWriter;
use App\Support\ProductCatalogue\CatalogueSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
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
     * blank. Week headers are appended after the fixed columns.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $weekHeaders
     */
    private function csv(array $rows, array $weekHeaders = []): string
    {
        $headers = array_merge(CatalogueSchema::fixedHeaders(), $weekHeaders);
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

        $rows = app(ProductCatalogueExport::class)->array();
        $mine = array_values(array_filter($rows, fn ($r): bool => (string) $r[0] === (string) $product->id));

        $this->assertCount(2, $mine, 'a 2-producer product spans 2 rows');
        // Main row first, full fields.
        $this->assertSame('Main', $mine[0][1]);
        $this->assertSame('Melchhof', $mine[0][2]);
        $this->assertSame('Obst & Gemüse', $mine[0][6]);   // Category
        $this->assertSame('SRC-9', $mine[0][4]);            // Sourcing ID
        $this->assertSame('note', $mine[0][46]);           // Comment (last fixed col)
        $this->assertSame('1.30', $mine[0][27]);           // Cost (primary buying price)
        // Secondary row: product-level cells blank, only Producer + Cost.
        $this->assertSame('', $mine[1][1]);
        $this->assertSame('Renner Hof', $mine[1][2]);
        $this->assertSame('', $mine[1][6]);                // no category on secondary
        $this->assertSame('1.50', $mine[1][27]);
    }

    public function test_export_route_downloads_csv(): void
    {
        $this->pieceProduct($this->producer('Melchhof'));

        $this->actingAs($this->admin())
            ->get(route('app.admin.products.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=erdgut-katalog-2026-07-08.csv');
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

        $this->assertSame(0, $result['summary']['to_create']);
        $this->assertSame(1, $result['summary']['to_update']);
        $this->assertSame([], $result['errors']);

        $plan = $result['products'][0]['data'];
        $this->assertSame(3.5, $plan['price']);
        $this->assertSame(2.9, $plan['business_price']);
        $this->assertSame('SRC-1', $plan['metadata']['sourcing_id']);
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
            ->post(route('app.admin.products.import.confirm'), ['token' => $token])
            ->assertRedirect(route('app.admin.products.index'));

        // Existing updated.
        $this->assertSame(9.99, (float) $existing->fresh()->unit_price);
        // New created with its group.
        $created = Product::query()->whereHas('translations', fn ($q) => $q->where('locale', 'de')->where('name', 'Neues Produkt'))->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->group_id);
        $this->assertSame('Frische Kräuter', $created->group->localizedName('de'));
        // Bad row skipped: no "Kaputt" product.
        $this->assertNull(Product::query()->whereHas('translations', fn ($q) => $q->where('name', 'Kaputt'))->first());
        // Stash cleaned up.
        $this->assertEmpty(Storage::disk('local')->files('catalogue-imports'));
    }

    // ------------------------------------------------------------- Module B

    public function test_weekly_column_writes_csv_owned_inflow_and_leaves_schedules(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, ['default_freshness_window_days' => 7]);

        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();
        $weekHeader = CatalogueSchema::weekHeader($monday);

        // A schedule-materialized batch in the same week must stay untouched.
        $schedule = ProductStockInflowSchedule::query()->create([
            'product_id' => $product->id,
            'producer_id' => $producer->id,
            'weekday' => (int) $monday->dayOfWeekIso,
            'planned_quantity' => 12,
            'freshness_window_days' => 7,
            'starts_on' => $monday->toDateString(),
        ]);
        $scheduled = ProductStockInflow::query()->create([
            'product_id' => $product->id,
            'producer_id' => $producer->id,
            'received_on' => $monday->toDateString(),
            'original_received_on' => $monday->toDateString(),
            'quantity' => 12,
            'freshness_window_days' => 7,
            'source_schedule_id' => $schedule->id,
        ]);

        $csv = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: ['Freshness (days)' => '7', $weekHeader => '5'])],
            [$weekHeader],
        );
        $this->applyCsv($csv);

        $csvInflow = ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->where('notes', ProductCatalogueImport::CSV_NOTE)
            ->whereDate('received_on', $monday->toDateString())
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
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: ['Freshness (days)' => '7', $weekHeader => 'Unlimited'])],
            [$weekHeader],
        );
        $this->applyCsv($csv2);
        $this->assertSame(1, ProductStockInflow::query()->where('notes', ProductCatalogueImport::CSV_NOTE)->count());
        $this->assertSame(
            (float) CatalogueSchema::UNLIMITED_SENTINEL,
            (float) $csvInflow->fresh()->quantity,
        );

        // "0" zeroes it.
        $csv3 = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'St', price: '2.50', extra: ['Freshness (days)' => '7', $weekHeader => '0'])],
            [$weekHeader],
        );
        $this->applyCsv($csv3);
        $this->assertSame(0.0, (float) $csvInflow->fresh()->quantity);
    }

    public function test_weekly_converts_kg_display_to_canonical_grams(): void
    {
        $producer = $this->producer('Melchhof');
        $product = $this->pieceProduct($producer, [
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.009, // 9 €/kg canonical
        ]);
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();
        $weekHeader = CatalogueSchema::weekHeader($monday);

        // 3 kg planned → 3000 g canonical.
        $csv = $this->csv(
            [$this->row($product->id, 'Melchhof', 'Bestehend', unit: 'kg', price: '9.00', extra: [$weekHeader => '3'])],
            [$weekHeader],
        );
        $this->applyCsv($csv);

        $inflow = ProductStockInflow::query()->where('notes', ProductCatalogueImport::CSV_NOTE)->first();
        $this->assertNotNull($inflow);
        $this->assertSame(3000.0, (float) $inflow->quantity);
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

    // ------------------------------------------------------------- helpers

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
