@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600']) }}>
