@extends('layouts.onboarding', ['step' => 'trial_delivery', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    {{-- C3 (UX notes): only the slots the fleet currently runs are
         shown. Today that's a single Wed 12-16 slot; once the
         delivery zones expand the picker auto-grows. --}}
    <p class="border-l-2 border-chapter-produce bg-brand-paper px-4 py-3 text-sm leading-relaxed text-brand-ink/70" data-testid="trial-delivery-zone-hint">
        {{ __('We currently deliver only Wednesdays 12–16 in Berlin. We try to honour your preferred date as closely as possible.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.trial_delivery.store') }}" class="mt-8 space-y-8">
        @csrf
        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Preferred timeslot') }}</legend>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach($timeslots as $slot)
                    <label class="flex cursor-pointer items-center justify-center border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="preferred_timeslot" value="{{ $slot }}" {{ ($data['preferred_timeslot'] ?? $timeslots[0]) === $slot ? 'checked' : '' }} class="sr-only">
                        <span class="text-sm font-medium text-brand-ink">{{ $slot }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('preferred_timeslot')" class="mt-3" />
        </fieldset>

        {{-- C4 (UX notes): real date picker bounded to the scheduling
             promise window. Operators get a parsable ISO date instead
             of "nächste Woche" / "asap" free-form German. --}}
        <div>
            <x-input-label for="preferred_delivery_date" :value="__('Preferred date')" optional />
            <x-text-input id="preferred_delivery_date" name="preferred_delivery_date" type="date"
                          :value="old('preferred_delivery_date', $data['preferred_delivery_date'] ?? '')"
                          :min="$minPreferredDate"
                          :max="$maxPreferredDate"
                          class="mt-2" />
            <p class="mt-1 text-xs text-brand-ink/65">{{ __('Pick any day from tomorrow up to :days days out — we will confirm a Wednesday slot close to your pick.', ['days' => max($promiseDays, 28)]) }}</p>
            <x-input-error :messages="$errors->get('preferred_delivery_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="special_instructions" :value="__('Anything else we should know?')" />
            <textarea id="special_instructions" name="special_instructions" rows="3"
                      class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0">{{ old('special_instructions', $data['special_instructions'] ?? '') }}</textarea>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-ink/10 pt-8">
            <a href="{{ route('app.onboarding.step.preferences') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('onboarding.cta.back') }}
            </a>
            <x-primary-button>{{ __('onboarding.cta.to_payment') }}</x-primary-button>
        </div>
    </form>
@endsection
