@php
    use App\Enums\OrderProductType;
    use App\Enums\PaymentChargeInitiator;
    use App\Enums\Timeslot;

    $weekdayLabels = [
        0 => __('Sunday'),
        1 => __('Monday'),
        2 => __('Tuesday'),
        3 => __('Wednesday'),
        4 => __('Thursday'),
        5 => __('Friday'),
        6 => __('Saturday'),
    ];
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('admin.user_detail.eyebrow') }}</p>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">
                    {{ $user->name }}
                </h2>
                <p class="text-sm text-gray-600">{{ $user->email }}</p>
            </div>
            <a href="{{ route('app.admin.users.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.user_detail.back_to_users') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Customer header card --}}
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_account') }}</h3>
                <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Name') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $user->name ?: '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Email') }}</dt>
                        <dd class="mt-1 break-all text-gray-900">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_phone') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $user->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_language') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ strtoupper((string) ($user->locale ?? 'de')) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_role') }}</dt>
                        <dd class="mt-1">
                            @if ($user->is_admin)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900">{{ __('admin.users.role_admin') }}</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ __('admin.users.role_user') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_email_verified') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $user->email_verified_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_created') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $user->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    @if ($user->is_test)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_test_account') }}</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center rounded bg-gray-200 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-gray-700">{{ __('admin.user_detail.badge_test') }}</span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Addresses --}}
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_addresses') }}</h3>
                @if ($user->addresses->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.user_detail.addresses_empty') }}</p>
                @else
                    <ul class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($user->addresses as $address)
                            <li class="rounded border border-gray-200 bg-gray-50 p-4 text-sm">
                                <p class="font-medium text-gray-900">
                                    {{ $address->recipient_name ?? '—' }}
                                    @if ($address->is_primary)
                                        <span class="ms-2 inline-flex items-center rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-900">{{ __('admin.user_detail.badge_primary') }}</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-gray-700">{{ $address->street_line_1 }}@if ($address->street_line_2), {{ $address->street_line_2 }}@endif</p>
                                <p class="text-gray-700">{{ $address->postal_code }} {{ $address->city }} · {{ strtoupper($address->country_code) }}</p>
                                @if ($address->phone)
                                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.user_detail.field_phone') }}: {{ $address->phone }}</p>
                                @endif
                                @if ($address->building_code || $address->floor)
                                    <p class="mt-1 text-xs text-gray-500">
                                        @if ($address->building_code){{ __('admin.user_detail.field_building_code') }}: {{ $address->building_code }}@endif
                                        @if ($address->building_code && $address->floor) · @endif
                                        @if ($address->floor){{ __('admin.user_detail.field_floor') }}: {{ $address->floor }}@endif
                                    </p>
                                @endif
                                @if ($address->delivery_instructions)
                                    <p class="mt-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-900">
                                        <strong>{{ __('admin.user_detail.field_delivery_notes') }}:</strong>
                                        {{ $address->delivery_instructions }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Subscription --}}
            @if ($primarySubscription)
                @php
                    $sub = $primarySubscription;
                    $eggs = (bool) data_get($sub->preferences, 'eggs_dairy_add_on', false);
                    $vegOnly = (bool) data_get($sub->preferences, 'veg_only', false);
                    $allergies = (array) ($sub->allergies ?? []);
                    $allergiesNote = (string) ($sub->allergies_note ?? '');
                @endphp
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_subscription') }}</h3>
                    <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_status') }}</dt>
                            <dd class="mt-1"><x-status-badge :value="$sub->status" type="subscription" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_plan') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ __('plan.'.$sub->plan->value) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_box_size') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ __(ucfirst($sub->box_size)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_recurring_amount') }}</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">@eur($sub->expectedChargeAmount())</dd>
                        </div>
                        @if ($sub->fulfillment_mode)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_fulfillment') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $sub->fulfillment_mode->label() }}</dd>
                            </div>
                        @endif
                        @if ($sub->delivery_weekday !== null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_delivery_weekday') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $weekdayLabels[$sub->delivery_weekday] ?? '—' }}</dd>
                            </div>
                        @endif
                        @if ($sub->preferred_timeslot)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_timeslot') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $sub->preferred_timeslot instanceof Timeslot ? $sub->preferred_timeslot->value : (string) $sub->preferred_timeslot }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.user_detail.field_next_charge') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $sub->next_charge_date?->toDateString() ?? '—' }}</dd>
                        </div>
                    </dl>

                    {{-- Preferences badges + allergies surfaced prominently. --}}
                    <div class="mt-5 flex flex-wrap items-start gap-2">
                        @if ($vegOnly)
                            <span class="inline-flex items-center rounded-full bg-chapter-produce/10 px-3 py-1 text-xs font-semibold text-chapter-produce">{{ __('admin.user_detail.badge_veg_only') }}</span>
                        @endif
                        @if ($eggs)
                            <span class="inline-flex items-center rounded-full bg-chapter-eggdairy/10 px-3 py-1 text-xs font-semibold text-chapter-eggdairy">{{ __('admin.user_detail.badge_eggs_addon') }}</span>
                        @endif
                        @forelse ($allergies as $allergen)
                            <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800">
                                {{ __('admin.user_detail.allergy_prefix') }}: {{ __('allergens.'.$allergen) }}
                            </span>
                        @empty
                            @if (! $eggs && ! $vegOnly)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-600">{{ __('admin.user_detail.no_special_preferences') }}</span>
                            @endif
                        @endforelse
                    </div>

                    @if ($allergiesNote !== '')
                        <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-900">{{ __('admin.user_detail.field_allergies_note') }}</p>
                            <p class="mt-1">{{ $allergiesNote }}</p>
                        </div>
                    @endif

                    @if ($user->subscriptions->count() > 1)
                        <p class="mt-4 text-xs text-gray-500">{{ __('admin.user_detail.note_multiple_subscriptions', ['count' => $user->subscriptions->count()]) }}</p>
                    @endif
                </section>
            @else
                <section class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-600">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_subscription') }}</h3>
                    <p class="mt-2">{{ __('admin.user_detail.subscription_empty') }}</p>
                </section>
            @endif

            {{-- Trial orders --}}
            @php
                $trialOrders = $orders->filter(fn ($o) => $o->product_type === OrderProductType::TrialBox);
            @endphp
            @if ($trialOrders->isNotEmpty())
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_trial_orders') }}</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pe-4">ID</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_status') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_box_size') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_amount') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_scheduled') }}</th>
                                    <th class="py-2 pe-0">{{ __('admin.user_detail.field_created') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($trialOrders as $order)
                                    <tr>
                                        <td class="py-2 pe-4 font-mono text-gray-700">
                                            <a href="{{ route('app.admin.orders.show', $order) }}" class="text-emerald-700 hover:underline">#{{ $order->id }}</a>
                                        </td>
                                        <td class="py-2 pe-4"><x-status-badge :value="$order->status" type="order" /></td>
                                        <td class="py-2 pe-4 text-gray-700">{{ __(ucfirst((string) $order->box_size)) }}</td>
                                        <td class="py-2 pe-4 tabular-nums text-gray-700">@eur($order->amount)</td>
                                        <td class="py-2 pe-4 text-gray-600">{{ $order->scheduled_for_date?->format('d.m.Y') ?? '—' }}</td>
                                        <td class="py-2 pe-0 text-gray-500">{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- Boxes --}}
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_boxes') }}</h3>
                @if ($boxes->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.user_detail.boxes_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_week') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_size') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_status') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_positions') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_value') }}</th>
                                    <th class="py-2 pe-0 text-right">{{ __('admin.user_detail.field_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($boxes as $box)
                                    <tr>
                                        <td class="py-2 pe-4 text-gray-900">{{ $box->week_start_date?->format('d.m.Y') }}</td>
                                        <td class="py-2 pe-4 text-gray-700">{{ __(ucfirst((string) $box->box_size)) }}</td>
                                        <td class="py-2 pe-4"><x-status-badge :value="$box->status" type="box" /></td>
                                        <td class="py-2 pe-4 tabular-nums text-gray-700">{{ $box->items->count() }}</td>
                                        <td class="py-2 pe-4 tabular-nums text-gray-700">@eur($box->estimated_value)</td>
                                        <td class="py-2 pe-0 text-right">
                                            <a href="{{ route('app.admin.boxes.show', $box) }}" class="text-emerald-700 hover:underline">{{ __('Open') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Deliveries --}}
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_deliveries') }}</h3>
                @if ($deliveries->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.user_detail.deliveries_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_scheduled') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_status') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_address') }}</th>
                                    <th class="py-2 pe-0 text-right">{{ __('admin.user_detail.field_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($deliveries as $delivery)
                                    <tr>
                                        <td class="py-2 pe-4 text-gray-900">{{ $delivery->scheduled_for_date?->format('d.m.Y') }}</td>
                                        <td class="py-2 pe-4"><x-status-badge :value="$delivery->status" type="delivery" /></td>
                                        <td class="py-2 pe-4 text-gray-600">
                                            @if ($delivery->address)
                                                {{ $delivery->address->street_line_1 }}, {{ $delivery->address->postal_code }} {{ $delivery->address->city }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="py-2 pe-0 text-right">
                                            <a href="{{ route('app.admin.deliveries.edit', $delivery) }}" class="text-emerald-700 hover:underline">{{ __('Edit') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Payments --}}
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_payments') }}</h3>
                @if ($payments->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.user_detail.payments_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pe-4">ID</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_amount') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_status') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_attempt') }}</th>
                                    <th class="py-2 pe-4">{{ __('admin.user_detail.field_paid_at') }}</th>
                                    <th class="py-2 pe-0 text-right">{{ __('admin.user_detail.field_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td class="py-2 pe-4 font-mono text-gray-700">#{{ $payment->id }}</td>
                                        <td class="py-2 pe-4 tabular-nums text-gray-700">@eur($payment->amount)</td>
                                        <td class="py-2 pe-4"><x-status-badge :value="$payment->status" type="payment" /></td>
                                        <td class="py-2 pe-4 tabular-nums text-gray-600">{{ $payment->attempt_number ?? '—' }}</td>
                                        <td class="py-2 pe-4 text-gray-600">{{ $payment->paid_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                        <td class="py-2 pe-0 text-right">
                                            <a href="{{ route('app.admin.payments.show', $payment) }}" class="text-emerald-700 hover:underline">{{ __('Open') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Audit timeline --}}
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.user_detail.section_audit') }}</h3>
                    <a href="{{ route('app.admin.audit-events.index', ['actor_user_id' => $user->id]) }}" class="text-xs text-emerald-700 hover:underline">{{ __('admin.user_detail.audit_full_log') }} →</a>
                </div>
                @if ($auditEvents->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.user_detail.audit_empty') }}</p>
                @else
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($auditEvents as $event)
                            @php
                                $isMailSent = $event->action === 'mail.sent';
                                $mailSubject = $isMailSent ? (string) data_get($event->metadata, 'subject', '') : null;
                                $mailRecipient = $isMailSent ? (string) data_get($event->metadata, 'recipient_email', '') : null;
                            @endphp
                            <li class="flex items-start gap-3 border-l-2 ps-3 {{ $isMailSent ? 'border-emerald-200' : 'border-gray-200' }}">
                                <span class="mt-0.5 text-xs text-gray-400 tabular-nums shrink-0">{{ $event->created_at?->format('d.m.Y H:i') }}</span>
                                <div class="min-w-0 flex-1">
                                    @if ($isMailSent)
                                        {{-- ERD-350: friendlier line for mail-sent events. The
                                             subject is the most useful trace for support
                                             ("did the customer get the trial confirmation?"). --}}
                                        <p class="text-gray-900">
                                            <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-emerald-800">
                                                {{ __('admin.user_detail.audit_mail_label') }}
                                            </span>
                                            <span class="ms-1 font-medium">{{ $mailSubject !== '' ? $mailSubject : __('admin.user_detail.audit_mail_subject_unknown') }}</span>
                                        </p>
                                        @if ($mailRecipient !== '')
                                            <p class="text-xs text-gray-500">{{ __('admin.user_detail.audit_mail_to', ['email' => $mailRecipient]) }}</p>
                                        @endif
                                    @else
                                        <p class="text-gray-900">
                                            <span class="font-mono text-xs text-gray-600">{{ $event->entity_type }}#{{ $event->entity_id }}</span>
                                            <span class="font-semibold">{{ $event->action }}</span>
                                            @if ($event->from_state || $event->to_state)
                                                <span class="text-xs text-gray-500">({{ $event->from_state ?? '—' }} → {{ $event->to_state ?? '—' }})</span>
                                            @endif
                                        </p>
                                        @if ($event->reason)
                                            <p class="text-xs text-gray-600">{{ $event->reason }}</p>
                                        @endif
                                    @endif
                                    @if ($event->actor)
                                        <p class="text-xs text-gray-400">{{ __('admin.user_detail.audit_actor', ['email' => $event->actor->email]) }}</p>
                                    @else
                                        <p class="text-xs text-gray-400">{{ __('admin.user_detail.audit_actor_system') }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

        </div>
    </div>
</x-admin-layout>
