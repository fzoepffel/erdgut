<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Your trial') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Your Erdgut trial box') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="border border-brand-ink/20 bg-brand-ink/5 px-4 py-3 text-sm" role="status">{{ session('status') }}</p>
            @endif

            <section class="border border-brand-ink/15 bg-white">
                <div class="border-b border-brand-ink/15 px-6 py-5 flex items-center justify-between">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Order') }} #{{ $order->id }}</p>
                        <p class="mt-1 text-sm text-brand-ink/70">{{ __('Status') }}: <span class="font-semibold text-brand-ink">{{ $order->status->label() }}</span></p>
                    </div>
                    <span class="font-display text-3xl font-semibold">EUR {{ number_format((float) $order->amount, 2) }}</span>
                </div>

                <dl class="px-6 py-5 grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-brand-ink/60">{{ __('Box size') }}</dt>
                        <dd class="mt-1 font-medium">{{ __(ucfirst($order->box_size)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-ink/60">{{ __('Delivery window') }}</dt>
                        <dd class="mt-1 font-medium">{{ $order->preferred_delivery_window ?: __('To be confirmed') }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-ink/60">{{ __('Scheduled for') }}</dt>
                        <dd class="mt-1 font-medium">
                            @if ($order->scheduled_for_date)
                                {{ $order->scheduled_for_date->format('D, d M Y') }}
                                @if ($order->preferred_timeslot) · {{ $order->preferred_timeslot }}@endif
                            @else
                                <span class="text-brand-ink/50">{{ __('Pending assignment by our team') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-brand-ink/60">{{ __('Address') }}</dt>
                        <dd class="mt-1">
                            @if ($order->address)
                                {{ $order->address->street_line_1 }}<br>
                                {{ $order->address->postal_code }} {{ $order->address->city }}
                            @else
                                <span class="text-brand-ink/50">{{ __('No address on file') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($order->box)
                    <div class="border-t border-brand-ink/10 px-6 py-5">
                        <p class="eyebrow text-chapter-produce">{{ __('What is in your box') }}</p>
                        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            @foreach ($order->box->items as $item)
                                <li class="flex justify-between border-b border-brand-ink/5 pb-1">
                                    <span>{{ $item->product?->name ?? '—' }}</span>
                                    <span class="text-brand-ink/60">{{ $item->quantity }} {{ $item->unit }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="border-t border-brand-ink/10 px-6 py-4 text-sm text-brand-ink/70">
                        {{ __('Your box contents will appear here once we compose it.') }}
                    </div>
                @endif
            </section>

            <section class="border border-chapter-produce/30 bg-chapter-produce/5 p-6">
                <p class="eyebrow text-chapter-produce">{{ __('Like what you got?') }}</p>
                <h2 class="mt-2 font-display text-2xl">{{ __('Turn your trial into a weekly box') }}</h2>
                <p class="mt-2 text-sm text-brand-ink/75">
                    {{ __('Keep your preferences and address — you just need to pick cadence and add a card.') }}
                </p>
                <a href="{{ route('app.onboarding.start', ['intent' => 'subscription', 'from_order' => $order->id]) }}"
                   class="mt-4 inline-flex items-center border border-chapter-produce bg-chapter-produce text-white px-5 py-2 text-sm font-semibold uppercase tracking-eyebrow hover:bg-chapter-produce/90"
                   data-testid="dashboard-trial-convert-cta">
                    {{ __('Start my subscription') }}
                </a>
            </section>
        </div>
    </div>
</x-app-layout>
