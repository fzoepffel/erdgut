<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn-ghost disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
