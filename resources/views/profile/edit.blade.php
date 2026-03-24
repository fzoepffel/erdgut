<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Account settings') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section aria-labelledby="settings-profile-heading">
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6 sm:p-8">
                        <div class="max-w-xl">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="settings-password-heading">
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6 sm:p-8">
                        <div class="max-w-xl">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="settings-danger-heading">
                <div class="overflow-hidden rounded-lg border-2 border-rose-200 bg-rose-50/50 shadow-sm">
                    <div class="p-6 sm:p-8">
                        <h2 id="settings-danger-heading" class="text-base font-semibold text-rose-900">
                            {{ __('Danger zone') }}
                        </h2>
                        <p class="mt-1 text-sm text-rose-800/90">
                            {{ __('Actions here permanently affect your account. You will be asked to confirm with your password.') }}
                        </p>
                        <div class="mt-6 max-w-xl border-t border-rose-200/80 pt-6">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
