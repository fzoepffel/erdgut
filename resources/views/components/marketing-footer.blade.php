<footer class="border-t border-brand-ink/15 bg-brand-cream">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-3">
            <div>
                <x-application-logo variant="filled" class="h-20 w-auto" />
                <p class="mt-5 text-sm leading-relaxed text-brand-ink/70">
                    {{ __('footer.tagline') }}
                </p>
            </div>

            <div>
                <p class="eyebrow text-chapter-produce">{{ __('footer.contact_heading') }}</p>
                <ul class="mt-4 space-y-2 text-sm text-brand-ink/80">
                    <li>
                        <a class="underline-offset-4 hover:underline" href="mailto:hallo@erdgut.de">{{ __('footer.contact_general') }}: hallo@erdgut.de</a>
                    </li>
                    <li>
                        <a class="underline-offset-4 hover:underline" href="mailto:bestellung@erdgut.de">{{ __('footer.contact_orders') }}: bestellung@erdgut.de</a>
                    </li>
                    <li>
                        <a class="underline-offset-4 hover:underline" href="mailto:einkauf@erdgut.de">{{ __('footer.contact_suppliers') }}: einkauf@erdgut.de</a>
                    </li>
                </ul>
            </div>

            <div>
                <p class="eyebrow text-chapter-produce">{{ __('footer.visit_heading') }}</p>
                <div class="mt-4 space-y-3 text-sm text-brand-ink/80">
                    <a href="https://instagram.com" target="_blank" rel="noopener" class="inline-flex items-center gap-2 underline-offset-4 hover:underline">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.17.055 1.97.24 2.43.402.61.236 1.04.517 1.5.977.458.458.74.89.975 1.5.162.46.35 1.26.403 2.43.058 1.266.07 1.647.07 4.85s-.012 3.585-.07 4.85c-.055 1.17-.24 1.97-.403 2.43-.236.61-.517 1.04-.976 1.5-.458.458-.89.74-1.5.976-.46.162-1.26.35-2.43.402-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.17-.055-1.97-.24-2.43-.402-.61-.236-1.04-.517-1.5-.976a4.02 4.02 0 0 1-.977-1.5c-.162-.46-.35-1.26-.402-2.43-.058-1.266-.07-1.646-.07-4.85s.012-3.584.07-4.85c.055-1.17.24-1.97.402-2.43.236-.61.517-1.04.977-1.5A4.02 4.02 0 0 1 4.72 2.635c.46-.162 1.26-.35 2.43-.402 1.266-.058 1.646-.07 4.85-.07M12 0C8.741 0 8.332.014 7.052.072 5.774.13 4.903.333 4.14.63a5.874 5.874 0 0 0-2.122 1.384A5.874 5.874 0 0 0 .63 4.14C.333 4.903.131 5.774.072 7.052.014 8.332 0 8.741 0 12s.014 3.668.072 4.948c.058 1.278.261 2.148.558 2.912.307.79.717 1.46 1.384 2.127a5.875 5.875 0 0 0 2.122 1.383c.764.297 1.635.5 2.913.558C8.332 23.986 8.741 24 12 24s3.668-.014 4.948-.072c1.278-.058 2.148-.261 2.912-.558a5.873 5.873 0 0 0 2.127-1.383 5.873 5.873 0 0 0 1.383-2.127c.297-.764.5-1.635.558-2.913.058-1.28.072-1.689.072-4.947s-.014-3.668-.072-4.948c-.058-1.278-.261-2.148-.558-2.912A5.874 5.874 0 0 0 21.987 2.02 5.873 5.873 0 0 0 19.86.635C19.096.333 18.226.13 16.948.072 15.668.014 15.258 0 12 0Zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324Zm0 10.162a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881Z" />
                        </svg>
                        Instagram
                    </a>
                    <p>{{ __('footer.visit_address') }}</p>
                </div>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-4 border-t border-brand-ink/10 pt-6 text-xs text-brand-ink/60 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} Erdgut. {{ __('footer.copyright_line') }}</p>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('public.about') }}" class="hover:text-brand-ink">{{ __('About') }}</a>
                <a href="{{ route('public.contact') }}" class="hover:text-brand-ink">{{ __('Contact') }}</a>
            </div>
        </div>
    </div>
</footer>
