<x-app-layout>
    <div class="pb-10 sm:pb-16">
        <div class="mx-auto max-w-4xl space-y-8 px-4 pt-8 sm:px-6 sm:pt-10 lg:px-8">
            <x-customer.hero-band
                chapter="producers"
                :image="asset('images/mock/hero/hero-poster.jpg')"
                :eyebrow="__('Account')"
                :title="__('Account settings')"
            />

            <x-customer.story-block
                chapter="producers"
                :quote="__('customer.story.profile.founders_intro')"
                :signature="__('customer.story.dashboard.founder_signature')"
            />

            <section id="profile" class="scroll-mt-24 border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="settings-profile-heading">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>

            <section id="password" class="scroll-mt-24 border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="settings-password-heading">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </section>

            <section id="danger" class="scroll-mt-24 border border-chapter-meat/40 bg-chapter-meat/5 p-6 sm:p-8" aria-labelledby="settings-danger-heading">
                <p class="eyebrow text-chapter-meat">{{ __('Danger zone') }}</p>
                <h2 id="settings-danger-heading" class="mt-2 font-display text-2xl text-brand-ink">
                    {{ __('Danger zone') }}
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/80">
                    {{ __('Actions here permanently affect your account. You will be asked to confirm with your password.') }}
                </p>
                <p class="mt-2 font-editorial text-sm italic text-brand-ink/60">
                    {{ __('customer.story.profile.danger_caption') }}
                </p>
                <div class="mt-6 max-w-xl border-t border-chapter-meat/30 pt-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
