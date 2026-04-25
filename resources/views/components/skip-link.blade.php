@props(['target' => 'main'])

{{-- Skip navigation link for keyboard users (WCAG 2.4.1 Bypass Blocks).
     Hidden until focused; gives keyboard/screen-reader users a way past the
     header & nav directly to the page's main content. --}}
<a
    href="#{{ $target }}"
    class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded focus:border focus:border-brand-ink focus:bg-brand-cream focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-brand-ink focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-chapter-produce focus:ring-offset-2"
>
    {{ __('Skip to main content') }}
</a>
