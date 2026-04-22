<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Account') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Account settings') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="settings-profile-heading">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>

            <section class="border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="settings-password-heading">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </section>

            <section class="border border-chapter-meat/40 bg-chapter-meat/5 p-6 sm:p-8" aria-labelledby="settings-danger-heading">
                <p class="eyebrow text-chapter-meat">{{ __('Danger zone') }}</p>
                <h2 id="settings-danger-heading" class="mt-2 font-display text-2xl text-brand-ink">
                    {{ __('Danger zone') }}
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/80">
                    {{ __('Actions here permanently affect your account. You will be asked to confirm with your password.') }}
                </p>
                <div class="mt-6 max-w-xl border-t border-chapter-meat/30 pt-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
