@props(['disabled' => false])

@php
    // Auto-wire aria-invalid + aria-describedby when the field has a validation
    // error. Convention: caller renders <x-input-error id="{id}-error" .../>
    // alongside this input — we point aria-describedby at it so screen readers
    // announce the error when the field is focused. Existing aria-describedby
    // values (e.g. helper-text id) are preserved by appending.
    $bag = $attributes->getAttributes();
    $inputName = $bag['name'] ?? null;
    $inputId = $bag['id'] ?? null;
    // $errors is shared by ShareErrorsFromSession middleware; defensive null
    // guard so the component still renders if used outside a request context.
    $hasError = $inputName !== null && isset($errors) && $errors->has($inputName);

    if ($hasError && $inputId !== null) {
        $errorId = $inputId . '-error';
        $existing = isset($bag['aria-describedby']) ? trim((string) $bag['aria-describedby']) : '';
        $bag['aria-describedby'] = $existing === '' ? $errorId : $existing . ' ' . $errorId;
        $attributes = new \Illuminate\View\ComponentAttributeBag($bag);
    }
@endphp

<input
    @disabled($disabled)
    @if ($hasError) aria-invalid="true" @endif
    {{ $attributes->merge(['class' => 'w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0']) }}
>
