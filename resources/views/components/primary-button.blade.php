<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-solid disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
