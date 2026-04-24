<?php

namespace App\Models;

use App\Console\Commands\ChargeSubscriptions;
use App\Enums\BillingMode;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\Timeslot;
use App\Services\Billing\BoxPricing;
use Database\Factories\SubscriptionFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'sumup_token',
        'sumup_customer_id',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'payment_mandate_id',
        'address_id',
        'delivery_weekday',
        'preferred_timeslot',
        'fulfillment_mode',
        'billing_mode',
        'plan',
        'box_size',
        'preferences',
        'allergies',
        'allergies_note',
        'status',
        'next_charge_date',
        'pause_from',
        'pause_until',
        'cancelled_at',
        'cancellation_reason',
        'sumup_token',
        'sumup_customer_id',
        'is_test',
        'trial_completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'billing_mode' => BillingMode::class,
            'fulfillment_mode' => FulfillmentMode::class,
            'delivery_weekday' => 'integer',
            'preferred_timeslot' => Timeslot::class,
            'preferences' => 'array',
            'allergies' => 'array',
            'next_charge_date' => 'date',
            'pause_from' => 'date',
            'pause_until' => 'date',
            'cancelled_at' => 'datetime',
            'trial_completed_at' => 'datetime',
            'is_test' => 'boolean',
            'sumup_token' => 'encrypted',
            'sumup_customer_id' => 'encrypted',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment mandate used to charge this subscription, if any.
     *
     * Null for manual billing modes.
     */
    public function paymentMandate(): BelongsTo
    {
        return $this->belongsTo(PaymentMandate::class);
    }

    /**
     * Active SumUp token for this subscription. Prefers the linked mandate,
     * falls back to the legacy `sumup_token` column for subscriptions that
     * predate the mandate cutover. Returns null if no credentials are
     * available (e.g. manual billing modes, revoked mandate, never set up).
     */
    public function activeSumupToken(): ?string
    {
        $mandate = $this->relationLoaded('paymentMandate')
            ? $this->paymentMandate
            : $this->paymentMandate()->first();

        if ($mandate !== null && $mandate->isActive() && $mandate->provider_token !== null && $mandate->provider_token !== '') {
            return $mandate->provider_token;
        }

        $legacy = trim((string) $this->sumup_token);

        return $legacy === '' ? null : $legacy;
    }

    /**
     * Active SumUp customer id. Same precedence as {@see activeSumupToken}.
     */
    public function activeSumupCustomerId(): ?string
    {
        $mandate = $this->relationLoaded('paymentMandate')
            ? $this->paymentMandate
            : $this->paymentMandate()->first();

        if ($mandate !== null && $mandate->isActive() && $mandate->provider_customer_id !== null && $mandate->provider_customer_id !== '') {
            return $mandate->provider_customer_id;
        }

        $legacy = trim((string) $this->sumup_customer_id);

        return $legacy === '' ? null : $legacy;
    }

    /**
     * Whether this subscription can be charged automatically via SumUp.
     */
    public function hasActiveSumupCredentials(): bool
    {
        return $this->activeSumupToken() !== null && $this->activeSumupCustomerId() !== null;
    }

    /**
     * Get the address for this subscription's deliveries.
     *
     * May be null — in that case the delivery projector falls back to
     * the user's primary address at delivery-creation time.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get all boxes composed for this subscription.
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }

    /**
     * Get all one-off skip weeks scheduled for this subscription.
     */
    public function skipWeeks(): HasMany
    {
        return $this->hasMany(SubscriptionSkipWeek::class);
    }

    /**
     * Get all payment attempts for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Set subscription state to active.
     */
    public function activate(): void
    {
        $this->update(['status' => SubscriptionStatus::Active]);
    }

    /**
     * Set subscription state to paused.
     */
    public function pause(): void
    {
        $this->update(['status' => SubscriptionStatus::Paused]);
    }

    /**
     * Set subscription state to cancelled.
     */
    public function cancel(): void
    {
        $this->update(['status' => SubscriptionStatus::Cancelled]);
    }

    /**
     * Subscription row used for billing and payment flows (prefers active status, then latest).
     */
    public static function billingSubscriptionForUser(Authenticatable $user): ?self
    {
        return static::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByRaw('status = ? desc', [SubscriptionStatus::Active->value])
            ->latest()
            ->first();
    }

    public function hasPauseWindow(): bool
    {
        return $this->pause_from !== null && $this->pause_until !== null;
    }

    public function isWithinPauseWindow(?Carbon $date = null): bool
    {
        if (! $this->hasPauseWindow()) {
            return false;
        }

        $targetDate = ($date ?? now())->toDateString();

        return $targetDate >= $this->pause_from->toDateString()
            && $targetDate <= $this->pause_until->toDateString();
    }

    /**
     * Whether the one-time trial price still applies before the first paid recurring charge.
     *
     * Trial uses {@see config('box.trial_price_ttc')} (TTC) for the charge and box budget,
     * regardless of box_size, until {@see $trial_completed_at} is set when a recurring
     * payment is marked paid (webhook).
     */
    public function isTrialBillingPending(): bool
    {
        if (! config('box.trial_billing_enabled', true)) {
            return false;
        }

        return $this->trial_completed_at === null;
    }

    public function hasEggsDairyAddOn(): bool
    {
        return (bool) data_get($this->preferences, 'eggs_dairy_add_on', false);
    }

    /**
     * Configured eggs/dairy add-on EUR when the preference is enabled.
     */
    public function eggsDairyAddOnAmount(): float
    {
        if (! $this->hasEggsDairyAddOn()) {
            return 0.0;
        }

        $raw = config('box.eggs_dairy_add_on_eur');

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    /**
     * Base TTC for the subscription before add-ons: trial price or size-based included value.
     */
    public function baseBillingAmountTtc(): float
    {
        return $this->isTrialBillingPending()
            ? BoxPricing::subscriptionTrialFlatTtc()
            : BoxPricing::sizeBaseTtc((string) $this->box_size);
    }

    /**
     * Expected recurring charge amount (EUR TTC): base (trial or size) plus optional eggs/dairy add-on.
     * Matches {@see ChargeSubscriptions} and admin manual charge.
     */
    public function expectedChargeAmount(): float
    {
        return BoxPricing::subscriptionRecurringTotal($this);
    }

    /**
     * Included value for box budget / remainder (same TTC as charged, including add-on).
     */
    public function includedValueForBudget(): float
    {
        return BoxPricing::subscriptionIncludedValueForBudget($this);
    }

    /**
     * Nominal included TTC for the subscription's box size (config only; ignores trial and add-ons).
     */
    public function nominalIncludedValueTtc(): float
    {
        return BoxPricing::sizeBaseTtc((string) $this->box_size);
    }

    /**
     * Mark trial window closed after first successful recurring payment (idempotent).
     */
    public function markTrialBillingCompletedIfPending(): void
    {
        if ($this->trial_completed_at !== null) {
            return;
        }

        $this->forceFill(['trial_completed_at' => now()])->save();
    }
}
