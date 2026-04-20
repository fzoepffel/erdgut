<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-danger disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
