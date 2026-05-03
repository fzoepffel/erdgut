<div class="space-y-6">
    <header>
        <h3 class="font-display text-xl text-brand-ink">
            {{ __('Delete account') }}
        </h3>

        <p class="mt-2 text-sm leading-relaxed text-brand-ink/75">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('account.profile.destroy') }}" class="bg-brand-cream p-6" aria-labelledby="confirm-deletion-title">
            @csrf
            @method('delete')

            <p class="eyebrow text-chapter-meat">{{ __('Confirm') }}</p>
            <h2 id="confirm-deletion-title" class="mt-2 font-display text-2xl text-brand-ink">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p id="confirm-deletion-desc" class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full max-w-md"
                    placeholder="{{ __('Password') }}"
                    autocomplete="current-password"
                    aria-describedby="confirm-deletion-desc"
                />

                <x-input-error id="password-error" :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Delete account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</div>
