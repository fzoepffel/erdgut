<footer class="border-t border-brand-ink/15 bg-brand-cream">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <x-triple-star class="opacity-70" />
        <div class="mt-10 grid gap-8 md:grid-cols-3 md:items-start">
            <div class="flex flex-col items-start gap-3">
                <x-application-logo variant="filled" :withWordmark="false" class="h-16 w-16" />
                <p class="font-editorial text-sm italic leading-relaxed text-brand-ink/70">
                    {{ __('footer.tagline') }}
                </p>
            </div>

            <div>
                <p class="eyebrow text-chapter-produce">{{ __('footer.contact_heading') }}</p>
                <ul class="mt-4 space-y-2 text-sm text-brand-ink/80">
                    <li>
                        <a class="underline-offset-4 hover:underline" href="mailto:kontakt@erd-gut.de">kontakt@erd-gut.de</a>
                    </li>
                </ul>
            </div>

            <div>
                <p class="eyebrow text-chapter-producers">{{ __('The book') }}</p>
                <ul class="mt-4 space-y-2 text-sm text-brand-ink/80">
                    <li><a class="underline-offset-4 hover:underline" href="{{ route('public.about') }}">{{ __('About') }}</a></li>
                    <li><a class="underline-offset-4 hover:underline" href="{{ route('public.producers') }}">{{ __('Producers') }}</a></li>
                    <li><a class="underline-offset-4 hover:underline" href="{{ route('public.contact') }}">{{ __('Contact') }}</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-3 border-t border-brand-ink/10 pt-6 text-xs text-brand-ink/60 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} Erdgut</p>
            <p class="font-editorial italic">{{ __('In the rhythm of the seasons.') }}</p>
        </div>
    </div>
</footer>
