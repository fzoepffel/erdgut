<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionSkipWeeksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'weeks' => ['nullable', 'array', 'max:12'],
            'weeks.*' => ['date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $weeks = (array) $this->input('weeks', []);

        $this->merge([
            'weeks' => collect($weeks)
                ->map(static fn ($value): string => (string) $value)
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $weeks = (array) $this->validated()['weeks'] ?? [];
            $today = CarbonImmutable::today();
            $horizonEnd = $today->copy()->addWeeks(12)->startOfWeek();

            foreach ($weeks as $index => $value) {
                $date = CarbonImmutable::parse($value);
                if (! $date->isMonday()) {
                    $validator->errors()->add("weeks.$index", __('Each skipped week must start on a Monday.'));

                    continue;
                }

                if ($date->isBefore($today->copy()->startOfWeek())) {
                    $validator->errors()->add("weeks.$index", __('Skipped weeks must not be in the past.'));

                    continue;
                }

                if ($date->greaterThan($horizonEnd)) {
                    $validator->errors()->add("weeks.$index", __('Skipped weeks must fall within the next 12 weeks.'));
                }
            }
        });
    }
}
