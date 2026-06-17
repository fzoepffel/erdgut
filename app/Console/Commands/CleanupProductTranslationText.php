<?php

namespace App\Console\Commands;

use App\Models\ProductTranslation;
use App\Support\HumanText;
use Illuminate\Console\Command;

class CleanupProductTranslationText extends Command
{
    protected $signature = 'product-translations:cleanup-text
                            {--dry-run : Show rows that would change without writing}
                            {--force : Required to persist changes}';

    protected $description = 'Decode accidental JSON/HTML escapes in product translation text fields.';

    /**
     * @var list<string>
     */
    private array $fields = [
        'name',
        'origin',
        'variety',
        'short_description',
        'taste_profile',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Refusing to write without --force. Use --dry-run to preview.');

            return self::FAILURE;
        }

        $changedRows = 0;
        $changedFields = 0;

        ProductTranslation::query()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$changedRows, &$changedFields): void {
                foreach ($rows as $translation) {
                    $updates = [];

                    foreach ($this->fields as $field) {
                        $current = $translation->{$field};
                        if (! is_string($current) || $current === '') {
                            continue;
                        }

                        $normalized = HumanText::normalize($current);
                        if ($normalized !== $current) {
                            $updates[$field] = $normalized;
                            $changedFields++;
                        }
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $changedRows++;
                    $this->line(sprintf(
                        'product_id=%d locale=%s fields=%s',
                        $translation->product_id,
                        $translation->locale,
                        implode(',', array_keys($updates)),
                    ));

                    if (! $dryRun) {
                        $translation->update($updates);
                    }
                }
            });

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$changedRows} translation row(s), {$changedFields} field(s).");

        return self::SUCCESS;
    }
}
