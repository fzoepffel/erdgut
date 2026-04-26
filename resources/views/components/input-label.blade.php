@props(['value', 'required' => false, 'optional' => false])

<label {{ $attributes->merge(['class' => 'block eyebrow text-brand-ink/80']) }}>
    {{ $value ?? $slot }}@if($required) <span class="text-chapter-meat" aria-hidden="true">*</span><span class="sr-only"> {{ __('required') }}</span>@elseif($optional) <span class="ml-1 text-[0.65rem] font-normal normal-case tracking-normal text-brand-ink/45">({{ __('optional') }})</span>@endif
</label>
