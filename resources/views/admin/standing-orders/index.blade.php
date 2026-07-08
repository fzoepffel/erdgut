<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.admin_standing_title') }}</h1>
            <span class="text-sm text-gray-500">{{ trans_choice('restaurant.admin_standing_subtitle', $standingOrders->count(), ['count' => $standingOrders->count()]) }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ __('restaurant.standing_flash_'.session('status')) }}</div>
            @endif

            @php $weekdayName = fn (int $w) => \Illuminate\Support\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($w)->locale(app()->getLocale())->isoFormat('dddd'); @endphp

            @if ($standingOrders->isEmpty())
                <div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">{{ __('restaurant.admin_standing_empty') }}</div>
            @endif

            @foreach ($standingOrders as $so)
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm @if ($so->status->value === 'cancelled') opacity-60 @endif"
                     id="standing-{{ $so->id }}" data-testid="admin-standing-{{ $so->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">
                                {{ $so->user?->restaurantProfile?->business_name ?: ($so->user?->name ?? '') }}
                                <span class="font-normal text-gray-400">· {{ $so->user?->email }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('restaurant.standing_weekly_on', ['day' => $weekdayName($so->delivery_weekday)]) }}
                                · {{ __('restaurant.admin_standing_next_delivery') }}:
                                @if ($nextDeliveries->has($so->id))
                                    {{ \Illuminate\Support\Carbon::parse($nextDeliveries[$so->id])->translatedFormat('d.m.Y') }}
                                @else
                                    {{ __('restaurant.admin_standing_no_next') }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400">{{ trim(implode(', ', array_filter([$so->delivery_street, trim(($so->delivery_postal_code ?? '').' '.($so->delivery_city ?? ''))])), ', ') }}</p>
                        </div>
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[11px] font-medium',
                            'bg-emerald-100 text-emerald-800' => $so->isActive(),
                            'bg-amber-100 text-amber-800' => $so->status->value === 'paused' || $so->isPending(),
                            'bg-gray-100 text-gray-600' => $so->status->value === 'cancelled',
                        ])>{{ __('restaurant.standing_status_'.$so->status->value) }}</span>
                    </div>

                    @if ($so->status->value !== 'cancelled')
                        {{-- Template editor: new quantities apply to all future weeks;
                             hand-adjusted or already-closed weeks stay untouched. --}}
                        <form method="POST" action="{{ route('app.admin.standing-orders.items', $so) }}">
                            @csrf
                            @error('lines')<p class="mt-3 text-xs text-red-600">{{ $message }}</p>@enderror
                            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                                @foreach ($so->items as $i => $item)
                                    <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                        <span class="min-w-0 flex-1 truncate text-gray-800">{{ $item->displayName() }}</span>
                                        <span class="flex items-center gap-2">
                                            <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $item->id }}">
                                            <input type="number" step="{{ $item->product?->effectiveQuantityStep() ?? 1 }}" min="0"
                                                   name="lines[{{ $i }}][quantity]"
                                                   value="{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}"
                                                   data-testid="admin-standing-qty-{{ $item->id }}"
                                                   class="w-24 rounded-md border-gray-300 px-2 py-1 text-right text-xs">
                                            <span class="w-9 text-xs text-gray-500">{{ $item->product_id === null ? \App\Enums\ProductUnit::displaySymbol((string) $item->unit) : $item->product?->canonicalUnitLabel() }}</span>
                                            @if ($so->items->count() > 1)
                                                <label class="flex items-center gap-1 text-xs text-gray-500" title="{{ __('restaurant.standing_remove_line_hint') }}">
                                                    <input type="checkbox" name="lines[{{ $i }}][remove]" value="1" class="rounded border-gray-300">
                                                    {{ __('restaurant.standing_remove_line') }}
                                                </label>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <button type="submit" data-testid="admin-standing-items-save-{{ $so->id }}"
                                        class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:border-emerald-500 hover:text-emerald-700">
                                    {{ __('restaurant.standing_items_save') }}
                                </button>
                                <p class="text-[11px] text-gray-400">{{ __('restaurant.standing_items_note') }}</p>
                            </div>
                        </form>

                        {{-- End date: any future date works; the trim and the
                             materializer only act on weekday occurrences before it. --}}
                        <form method="POST" action="{{ route('app.admin.standing-orders.end', $so) }}" class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                            @csrf
                            <label class="text-xs font-medium text-gray-500" for="admin-ends-{{ $so->id }}">{{ __('restaurant.standing_end_label') }}</label>
                            <input type="date" id="admin-ends-{{ $so->id }}" name="ends_on"
                                   value="{{ $so->items->first()?->ends_on?->toDateString() }}"
                                   min="{{ today()->toDateString() }}"
                                   class="rounded-md border-gray-300 py-1 text-xs">
                            <button type="submit" data-testid="admin-standing-end-save-{{ $so->id }}"
                                    class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:border-emerald-500 hover:text-emerald-700">
                                {{ __('restaurant.standing_end_save') }}
                            </button>
                            <x-input-error :messages="$errors->get('ends_on')" class="mt-1" />
                        </form>

                        <div class="mt-4 flex items-center gap-4 text-xs font-medium">
                            @if ($so->isPending())
                                <form method="POST" action="{{ route('app.admin.standing-orders.activate', $so) }}"
                                      onsubmit="return confirm(@js(__('restaurant.admin_standing_activate_confirm')))">
                                    @csrf
                                    <button data-testid="admin-standing-activate-{{ $so->id }}" class="rounded-md bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700">{{ __('restaurant.admin_standing_activate') }}</button>
                                </form>
                            @elseif ($so->isActive())
                                <form method="POST" action="{{ route('app.admin.standing-orders.pause', $so) }}">@csrf
                                    <button data-testid="admin-standing-pause-{{ $so->id }}" class="text-gray-500 hover:text-emerald-700">{{ __('restaurant.standing_pause') }}</button>
                                </form>
                            @elseif ($so->status->value === 'paused')
                                <form method="POST" action="{{ route('app.admin.standing-orders.resume', $so) }}">@csrf
                                    <button data-testid="admin-standing-resume-{{ $so->id }}" class="text-emerald-700 hover:underline">{{ __('restaurant.standing_resume') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('app.admin.standing-orders.destroy', $so) }}"
                                  onsubmit="return confirm(@js(__('restaurant.standing_delete_confirm')))">
                                @csrf @method('DELETE')
                                <button data-testid="admin-standing-cancel-{{ $so->id }}" class="text-red-600 hover:underline">{{ __('restaurant.standing_delete') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
