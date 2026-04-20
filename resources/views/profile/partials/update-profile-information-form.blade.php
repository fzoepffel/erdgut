<section>
    <header>
        <p class="eyebrow text-category-produce">{{ __('Profile') }}</p>
        <h2 id="settings-profile-heading" class="mt-2 font-display text-2xl text-brand-ink">
            {{ __('Profile and contact') }}
        </h2>

        <p class="mt-2 text-sm leading-relaxed text-brand-ink/75">
            {{ __('Update your name and email. These details are used for deliveries and account notifications.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('account.profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-2 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            <p class="mt-2 text-sm text-brand-ink/70">
                {{ __('If you change your email, we will reset email verification. You must verify the new address before some protected actions work again.') }}
            </p>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3">
                    <p class="text-sm text-brand-ink">
                        {{ __('Your email address is unverified.') }}

                        <button
                            form="send-verification"
                            type="submit"
                            class="font-medium text-brand-ink underline underline-offset-4 hover:text-category-produce focus:outline-none focus:ring-2 focus:ring-category-produce focus:ring-offset-2"
                        >
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-category-produce" role="status">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-brand-ink/70"
                    role="status"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
