@props(['value'])

<label {{ $attributes->merge(['class' => 'block eyebrow text-brand-ink/80']) }}>
    {{ $value ?? $slot }}
</label>
