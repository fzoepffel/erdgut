<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminEmailResendRequest;
use App\Mail\BoxComposedMail;
use App\Mail\DeliveredMail;
use App\Mail\DeliveryInTransitMail;
use App\Mail\SubscriptionActivatedMail;
use App\Mail\SubscriptionCancellationMail;
use App\Mail\SubscriptionPauseExhaustedMail;
use App\Mail\SubscriptionSkipConfirmationMail;
use App\Mail\TrialConvertToSubscriptionMail;
use App\Mail\TrialDeliveryScheduledMail;
use App\Mail\TrialOrderConfirmationMail;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * ERD-332: one admin endpoint to re-dispatch any known transactional email.
 *
 * The admin picks a template (see {@see AdminEmailResendRequest::TEMPLATES}) and
 * supplies the relevant entity id; we resolve the model, instantiate the matching
 * Mailable, and queue it to the entity's user. Every resend writes an AuditEvent
 * so operations can trace why a customer received a duplicate email.
 */
class AdminEmailResendController extends Controller
{
    public function store(AdminEmailResendRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $template = (string) $request->validated('template');
        $actorId = (int) $request->user()->id;

        [$mailable, $recipientEmail, $entityType, $entityId] = $this->resolve($template, $request);

        if ($mailable === null || $recipientEmail === null || $recipientEmail === '') {
            return redirect()
                ->back()
                ->with('status', 'admin-email-resend-not-found');
        }

        Mail::to($recipientEmail)->queue($mailable);

        AuditLogger::log(
            entityType: $entityType,
            entityId: $entityId,
            action: 'email_resend',
            metadata: [
                'template' => $template,
                'recipient' => $recipientEmail,
            ],
            actorUserId: $actorId,
        );

        return redirect()
            ->back()
            ->with('status', 'admin-email-resent');
    }

    /**
     * @return array{0: ?Mailable, 1: ?string, 2: string, 3: int}
     */
    private function resolve(string $template, AdminEmailResendRequest $request): array
    {
        return match ($template) {
            'subscription.activated' => $this->fromSubscription($request, fn (Subscription $s) => new SubscriptionActivatedMail($s)),
            'subscription.skip_confirmation' => $this->fromSubscription($request, function (Subscription $s) {
                $skipWeeks = $s->skipWeeks()
                    ->where('source', SubscriptionSkipWeek::SOURCE_USER)
                    ->orderBy('week_start_date')
                    ->pluck('week_start_date')
                    ->map(fn ($date) => (string) $date?->toDateString())
                    ->all();

                return new SubscriptionSkipConfirmationMail($s, array_values(array_filter($skipWeeks)));
            }),
            'subscription.cancellation' => $this->fromSubscription($request, fn (Subscription $s) => new SubscriptionCancellationMail($s)),
            'subscription.pause_exhausted' => $this->fromSubscription($request, fn (Subscription $s) => new SubscriptionPauseExhaustedMail($s)),
            'box.composed' => $this->fromBox($request),
            'delivery.in_transit' => $this->fromDelivery($request, fn (Delivery $d) => new DeliveryInTransitMail($d)),
            'delivery.delivered' => $this->fromDelivery($request, fn (Delivery $d) => new DeliveredMail($d)),
            'order.trial_confirmation' => $this->fromOrder($request, fn (Order $o) => new TrialOrderConfirmationMail($o)),
            'order.trial_delivery_scheduled' => $this->fromOrder($request, fn (Order $o) => new TrialDeliveryScheduledMail($o)),
            'order.trial_convert' => $this->fromOrder($request, fn (Order $o) => new TrialConvertToSubscriptionMail($o)),
            default => [null, null, 'unknown', 0],
        };
    }

    /**
     * @param  callable(Subscription): Mailable  $factory
     * @return array{0: ?Mailable, 1: ?string, 2: string, 3: int}
     */
    private function fromSubscription(AdminEmailResendRequest $request, callable $factory): array
    {
        $subscriptionId = (int) ($request->validated('subscription_id') ?? 0);
        if ($subscriptionId === 0) {
            return [null, null, 'subscription', 0];
        }

        $subscription = Subscription::query()->with('user')->find($subscriptionId);
        if ($subscription === null) {
            return [null, null, 'subscription', $subscriptionId];
        }

        return [$factory($subscription), $subscription->user?->email, 'subscription', $subscription->id];
    }

    /**
     * @return array{0: ?Mailable, 1: ?string, 2: string, 3: int}
     */
    private function fromBox(AdminEmailResendRequest $request): array
    {
        $boxId = (int) ($request->validated('box_id') ?? 0);
        if ($boxId === 0) {
            return [null, null, 'box', 0];
        }

        $box = Box::query()->with('user')->find($boxId);
        if ($box === null) {
            return [null, null, 'box', $boxId];
        }

        return [new BoxComposedMail($box), $box->user?->email, 'box', $box->id];
    }

    /**
     * @param  callable(Delivery): Mailable  $factory
     * @return array{0: ?Mailable, 1: ?string, 2: string, 3: int}
     */
    private function fromDelivery(AdminEmailResendRequest $request, callable $factory): array
    {
        $deliveryId = (int) ($request->validated('delivery_id') ?? 0);
        if ($deliveryId === 0) {
            return [null, null, 'delivery', 0];
        }

        $delivery = Delivery::query()->with('user')->find($deliveryId);
        if ($delivery === null) {
            return [null, null, 'delivery', $deliveryId];
        }

        return [$factory($delivery), $delivery->user?->email, 'delivery', $delivery->id];
    }

    /**
     * @param  callable(Order): Mailable  $factory
     * @return array{0: ?Mailable, 1: ?string, 2: string, 3: int}
     */
    private function fromOrder(AdminEmailResendRequest $request, callable $factory): array
    {
        $orderId = (int) ($request->validated('order_id') ?? 0);
        if ($orderId === 0) {
            return [null, null, 'order', 0];
        }

        $order = Order::query()->with('user')->find($orderId);
        if ($order === null) {
            return [null, null, 'order', $orderId];
        }

        return [$factory($order), $order->user?->email, 'order', $order->id];
    }
}
