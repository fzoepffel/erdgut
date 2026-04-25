<ul
    class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-brand-ink/60"
    role="list"
    aria-label="{{ __('Payment security') }}"
>
    <li class="flex items-center gap-1.5">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M10 2.5 4 5v5c0 3.5 2.5 6.5 6 7.5 3.5-1 6-4 6-7.5V5l-6-2.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            <path d="M7.5 10l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>{{ __('Secured by SumUp') }}</span>
    </li>
    <li class="flex items-center gap-1.5">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <rect x="3.5" y="8.5" width="13" height="8" rx="1" stroke="currentColor" stroke-width="1.3"/>
            <path d="M6.5 8.5V6a3.5 3.5 0 0 1 7 0v2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
        </svg>
        <span>{{ __('PCI DSS compliant') }}</span>
    </li>
    <li class="flex items-center gap-1.5">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.3"/>
            <path d="M7 10.5l2 2 4-4.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>{{ __('Your card number is never stored on our servers') }}</span>
    </li>
</ul>
