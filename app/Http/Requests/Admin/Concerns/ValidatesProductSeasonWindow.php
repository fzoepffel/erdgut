<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Validator;

/**
 * Shared validation + normalization for a product's recurring yearly season
 * window (month + day, start and end). Used by Store/UpdateProductRequest.
 *
 * The window is only meaningful when `is_seasonal` is true. When seasonal and
 * any window field is filled, all four are required; each day must be valid
 * for its month (29 Feb allowed). When not seasonal, the window may be blank.
 */
trait ValidatesProductSeasonWindow
{
    /**
     * @return array<string, mixed>
     */
    protected function seasonWindowRules(): array
    {
        return [
            'season_start_month' => ['nullable', 'integer', 'between:1,12'],
            'season_start_day' => ['nullable', 'integer', 'between:1,31'],
            'season_end_month' => ['nullable', 'integer', 'between:1,12'],
            'season_end_day' => ['nullable', 'integer', 'between:1,31'],
        ];
    }

    /**
     * Coerce blank window inputs to null so partial/empty submissions don't
     * trip the integer rules.
     */
    protected function seasonWindowInput(): array
    {
        $clean = fn (string $key) => ($v = $this->input($key)) === '' || $v === null ? null : (int) $v;

        return [
            'season_start_month' => $clean('season_start_month'),
            'season_start_day' => $clean('season_start_day'),
            'season_end_month' => $clean('season_end_month'),
            'season_end_day' => $clean('season_end_day'),
        ];
    }

    protected function validateSeasonWindow(Validator $v): void
    {
        if (! $this->boolean('is_seasonal')) {
            return;
        }

        $fields = ['season_start_month', 'season_start_day', 'season_end_month', 'season_end_day'];
        $values = array_map(fn (string $f) => $this->input($f), $fields);
        $filled = array_filter($values, static fn ($x) => $x !== null && $x !== '');

        if ($filled === []) {
            return; // seasonal with no window = in season year-round
        }

        if (count($filled) < 4) {
            foreach ($fields as $f) {
                if (($this->input($f) === null || $this->input($f) === '')) {
                    $v->errors()->add($f, __('product.season_window_incomplete'));
                }
            }

            return;
        }

        // Validate each (month, day) is a real calendar date. Use a leap year
        // so 29 Feb is accepted.
        foreach ([['season_start_month', 'season_start_day'], ['season_end_month', 'season_end_day']] as [$mKey, $dKey]) {
            $m = (int) $this->input($mKey);
            $d = (int) $this->input($dKey);
            if ($m >= 1 && $m <= 12 && ! checkdate($m, $d, 2024)) {
                $v->errors()->add($dKey, __('product.season_window_invalid_day'));
            }
        }
    }
}
