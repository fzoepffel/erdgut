<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentDiagnosticsRequest extends FormRequest
{
    /**
     * Open intake — anyone can beacon. Throttled at the route level
     * (throttle:30,1) so a runaway client can't flood the table.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Allowed phase names. Mirrors the JS instrumentation in
     * resources/views/components/payment/sumup-widget.blade.php — keep in
     * sync. Anything else is dropped with a 422 so we don't accept
     * arbitrary strings into the table.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phase' => ['required', 'string', Rule::in([
                'mounted',
                'sdk_loaded',
                'sdk_failed',
                'widget_ready',
                'submit_clicked',
                'response_success',
                'response_error',
                'response_fail',
                'redirect_starting',
                'load_timeout',
                'mount_threw',
                'processing_heartbeat',
                'stalled',
                'user_reported',
            ])],
            'checkout_id' => ['nullable', 'string', 'max:128'],
            'intent' => ['nullable', 'string', 'max:32'],
            'ms_since_mount' => ['nullable', 'integer', 'min:0', 'max:7200000'],
            'ua_summary' => ['nullable', 'string', 'max:160'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
