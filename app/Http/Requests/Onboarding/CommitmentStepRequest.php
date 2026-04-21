<?php

namespace App\Http\Requests\Onboarding;

use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommitmentStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in([
                OnboardingDraftManager::INTENT_TRIAL,
                OnboardingDraftManager::INTENT_SUBSCRIPTION,
            ])],
        ];
    }
}
