@php
    /** @var list<\Illuminate\Support\Carbon> $weeks */
    /** @var list<array{subscription: \App\Models\Subscription, cells: list<array{state: string, payment_id: ?int}>}> $rows */
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.billing_coverage.title') }}
        </h2>
    </x-slot>

    <div
        class="py-8 sm:py-12"
        x-data="{
            chargeSubscriptionId: null,
            chargeWeekStart: '',
            chargeLabel: '',
            openCharge(subId, week, label) {
                this.chargeSubscriptionId = subId;
                this.chargeWeekStart = week;
                this.chargeLabel = label;
                $dispatch('open-modal', 'confirm-admin-billing-charge');
            },
        }"
    >
        <div class="mx-auto max-w-[100rem] space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('charge'))
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    {{ $errors->first('charge') }}
                </div>
            @endif

            <p class="text-sm text-gray-600">{{ __('admin.billing_coverage.intro') }}</p>

            <div class="flex flex-wrap gap-4 text-xs text-gray-700">
                <span><span class="me-1 inline-block h-3 w-3 rounded-sm bg-emerald-200"></span> {{ __('admin.billing_coverage.legend_paid') }}</span>
                <span><span class="me-1 inline-block h-3 w-3 rounded-sm bg-amber-200"></span> {{ __('admin.billing_coverage.legend_pending_manual') }}</span>
                <span><span class="me-1 inline-block h-3 w-3 rounded-sm bg-sky-200"></span> {{ __('admin.billing_coverage.legend_pending_scheduled') }}</span>
                <span><span class="me-1 inline-block h-3 w-3 rounded-sm bg-violet-200"></span> {{ __('admin.billing_coverage.legend_scheduled') }}</span>
                <span><span class="me-1 inline-block h-3 w-3 rounded-sm bg-gray-100"></span> {{ __('admin.billing_coverage.legend_empty') }}</span>
            </div>

            @if ($rows === [])
                <p class="text-sm text-gray-600">{{ __('admin.billing_coverage.no_subscriptions') }}</p>
            @else
                <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <caption class="sr-only">{{ __('admin.billing_coverage.table_caption') }}</caption>
                        <thead>
                            <tr class="text-left font-medium uppercase tracking-wider text-gray-500">
                                <th scope="col" class="sticky left-0 z-10 bg-white py-2 pe-3 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                    {{ __('admin.operations.subscription') }}
                                </th>
                                @foreach ($weeks as $week)
                                    <th scope="col" class="min-w-[5.5rem] py-2 pe-2 text-center">
                                        {{ $week->translatedFormat('d.m.') }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                @php($subscription = $row['subscription'])
                                <tr class="align-top">
                                    <th scope="row" class="sticky left-0 z-10 bg-white py-2 pe-3 text-left font-normal text-gray-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                        <span class="block max-w-[12rem] truncate">{{ $subscription->user?->email ?? '—' }}</span>
                                        <span class="text-gray-500">#{{ $subscription->id }}</span>
                                    </th>
                                    @foreach ($row['cells'] as $idx => $cell)
                                        @php($week = $weeks[$idx])
                                        <td class="py-2 pe-2 text-center">
                                            @if ($cell['state'] === 'paid')
                                                <span class="inline-block rounded px-1.5 py-0.5 font-medium text-emerald-900 bg-emerald-100" title="{{ __('admin.billing_coverage.state_paid') }}">
                                                    {{ __('admin.billing_coverage.badge_paid') }}
                                                </span>
                                                @if ($cell['payment_id'] !== null)
                                                    <a
                                                        href="{{ route('app.admin.payments.index', ['subscription_id' => $subscription->id, 'status' => \App\Models\Payment::STATUS_PAID]) }}"
                                                        class="mt-1 block text-[0.65rem] text-emerald-700 hover:underline"
                                                    >{{ __('admin.billing_coverage.link_payments') }}</a>
                                                @endif
                                            @elseif ($cell['state'] === 'pending_manual')
                                                <span class="inline-block rounded px-1.5 py-0.5 font-medium text-amber-900 bg-amber-100">{{ __('admin.billing_coverage.badge_pending_manual') }}</span>
                                            @elseif ($cell['state'] === 'pending_scheduled')
                                                <span class="inline-block rounded px-1.5 py-0.5 font-medium text-sky-900 bg-sky-100">{{ __('admin.billing_coverage.badge_pending') }}</span>
                                            @elseif ($cell['state'] === 'scheduled')
                                                <span class="inline-block rounded px-1.5 py-0.5 font-medium text-violet-900 bg-violet-100">{{ __('admin.billing_coverage.badge_scheduled') }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                            @if (
                                                filled($subscription->sumup_token)
                                                && in_array($cell['state'], ['scheduled', 'empty'], true)
                                            )
                                                <button
                                                    type="button"
                                                    class="mt-1 block w-full text-[0.65rem] font-medium text-gray-900 hover:underline"
                                                    x-on:click.prevent="openCharge({{ $subscription->id }}, @js($week->toDateString()), @js($week->translatedFormat('d.m.Y')))"
                                                >
                                                    {{ __('admin.billing_coverage.action_charge') }}
                                                </button>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <x-modal name="confirm-admin-billing-charge" focusable>
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('admin.billing_charge.modal_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('admin.billing_charge.modal_body') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-800" x-text="chargeLabel"></p>
                    <form method="post" action="{{ route('app.admin.billing.charge') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="subscription_id" x-bind:value="chargeSubscriptionId" />
                        <input type="hidden" name="week_start_date" x-bind:value="chargeWeekStart" />
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="confirm_charge" value="1" class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" required />
                            {{ __('admin.billing_charge.confirm_checkbox') }}
                        </label>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                x-on:click="$dispatch('close-modal', 'confirm-admin-billing-charge')"
                            >
                                {{ __('admin.billing_charge.cancel') }}
                            </button>
                            <button type="submit" class="inline-flex rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                {{ __('admin.billing_charge.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </x-modal>
        </div>
    </div>
</x-admin-layout>
