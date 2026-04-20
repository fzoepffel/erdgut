<footer class="border-t border-brand-ink/10 bg-brand-cream">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-xs text-brand-ink/60 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p>&copy; {{ date('Y') }} Erdgut</p>
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('public.about') }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ __('About') }}</a>
            <a href="{{ route('public.contact') }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ __('Contact') }}</a>
            <a href="mailto:hallo@erdgut.de" class="underline-offset-4 hover:text-brand-ink hover:underline">hallo@erdgut.de</a>
        </div>
    </div>
</footer>
