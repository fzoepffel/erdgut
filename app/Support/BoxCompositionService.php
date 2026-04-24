<?php

namespace App\Support;

use App\Mail\BoxComposedMail;
use App\Models\Box;
use App\Models\BoxItem;
use Illuminate\Support\Facades\Mail;

class BoxCompositionService
{
    public function __construct(
        private readonly BoxValueRecalculationService $recalculationService,
        private readonly WeeklyDistributionService $distribution = new WeeklyDistributionService
    ) {}

    /**
     * Fill a box with default products based on its size's composition rules.
     *
     * Allergen and dietary filtering flows from the box's snapshots so a box's
     * contents reflect the subscriber's preferences at the time the box was opened.
     *
     * @return int Number of items added.
     */
    public function compose(Box $box, string $boxSize): int
    {
        $rules = (array) config("box.composition_by_size.{$boxSize}", []);

        if ($rules === []) {
            return 0;
        }

        $allergies = array_values((array) ($box->allergies_snapshot ?? []));
        $preferences = (array) ($box->preferences_snapshot ?? []);

        $added = 0;

        foreach ($rules as $category => $count) {
            $products = $this->distribution->pickProducts(
                (string) $category,
                (int) $count,
                $allergies,
                $preferences,
            );

            foreach ($products as $product) {
                BoxItem::create([
                    'box_id' => $box->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit' => data_get($product->metadata, 'unit', 'piece'),
                    'is_extra' => false,
                ]);

                $added++;
            }
        }

        if ($added > 0) {
            $this->recalculationService->recalculate($box);

            $recipient = $box->user?->email;
            if ($recipient !== null && $recipient !== '') {
                Mail::to($recipient)->queue(new BoxComposedMail($box->fresh() ?? $box));
            }
        }

        return $added;
    }

    /**
     * Replace core (non-extra) preset lines, then fill from composition rules.
     * Shop extras (is_extra = true) are kept.
     *
     * @return int Number of items added by compose().
     */
    public function composePresetFromScratch(Box $box, string $boxSize): int
    {
        $box->items()->where('is_extra', false)->delete();

        return $this->compose($box, $boxSize);
    }
}
