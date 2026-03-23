<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class SubscriptionPauseRequest extends FormRequest
{
    private const MAX_PAUSE_WEEKS = 12;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pause_from' => ['required', 'date', 'after_or_equal:today'],
            'pause_until' => ['required', 'date', 'after_or_equal:pause_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->date('pause_from');
            $until = $this->date('pause_until');
            if ($from instanceof \DateTimeInterface && $until instanceof \DateTimeInterface) {
                $carbonFrom = Carbon::parse($from);
                $carbonUntil = Carbon::parse($until);
                if ($carbonFrom->diffInWeeks($carbonUntil) > self::MAX_PAUSE_WEEKS) {
                    $validator->errors()->add(
                        'pause_until',
                        __('Die Pausenzeit darf maximal :weeks Wochen betragen.', ['weeks' => self::MAX_PAUSE_WEEKS])
                    );
                }
            }
        });
    }
}
