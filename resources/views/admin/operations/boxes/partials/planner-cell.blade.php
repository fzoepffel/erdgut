@php
    use App\Enums\BoxStatus;
@endphp
@if (! $eligible)
    <span class="text-xs text-amber-800">{{ __('admin.box_planner.state_not_eligible') }}</span>
@elseif ($box === null)
    <span class="mb-2 block text-xs text-gray-600">{{ __('admin.box_planner.state_no_box') }}</span>
    <form method="post" action="{{ route('app.admin.boxes.store') }}" class="space-y-1">
        @csrf
        <input type="hidden" name="subscription_id" value="{{ $subscription->id }}" />
        <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />
        <button type="submit" class="w-full rounded bg-emerald-700 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-800">
            {{ __('admin.box_planner.action_create') }}
        </button>
    </form>
@else
    @if ($box->status === BoxStatus::Editable)
        <span class="mb-2 block text-xs font-medium text-emerald-800">{{ __('admin.box_planner.state_editable') }}</span>
    @elseif ($box->status === BoxStatus::Locked)
        <span class="mb-2 block text-xs text-gray-700">{{ __('admin.box_planner.state_locked') }}</span>
    @else
        <span class="mb-2 block text-xs text-gray-700">{{ __('admin.box_planner.state_delivered') }}</span>
    @endif
    <div class="flex flex-col gap-1">
        <a href="{{ route('app.admin.boxes.show', $box) }}" class="text-xs font-medium text-emerald-700 hover:underline">
            {{ __('admin.box_planner.action_details') }}
        </a>
        @if ($box->status === BoxStatus::Editable)
            <form method="post" action="{{ route('app.admin.boxes.preset', $box) }}" class="pt-1">
                @csrf
                <input type="hidden" name="subscription_id" value="{{ $subscription->id }}" />
                <button type="submit" class="w-full rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-800 hover:bg-gray-50">
                    {{ __('admin.box_planner.action_fill_preset') }}
                </button>
            </form>
        @endif
    </div>
@endif
