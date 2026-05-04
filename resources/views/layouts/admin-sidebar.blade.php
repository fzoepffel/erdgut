{{-- Persistent backoffice sidebar; parent layout must wrap with x-data="{ adminSidebarOpen: false }". --}}
<div
    x-show="adminSidebarOpen"
    x-transition.opacity
    class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
    @click="adminSidebarOpen = false"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-amber-200 bg-white shadow-xl transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 lg:shadow-none"
    :class="adminSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    aria-label="{{ __('admin.shell.sidebar_label') }}"
>
    <div class="flex h-20 shrink-0 items-center border-b border-amber-100 px-4">
        <a
            href="{{ route('app.admin.dashboard') }}"
            class="flex items-center gap-2 text-amber-950"
            @click="adminSidebarOpen = false"
        >
            <x-application-logo :withWordmark="false" class="block h-12 w-auto" />
            <span class="text-sm font-semibold tracking-tight">{{ config('app.name') }}</span>
        </a>
        <button
            type="button"
            class="ml-auto rounded-md p-2 text-gray-500 hover:bg-amber-50 hover:text-gray-700 lg:hidden"
            @click="adminSidebarOpen = false"
        >
            <span class="sr-only">{{ __('admin.shell.close_menu') }}</span>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="{{ __('admin.shell.nav_primary') }}">
        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_main') }}
        </p>
        <x-admin.nav-link :href="route('app.admin.dashboard')" matches="app.admin.dashboard">
            {{ __('admin.shell.overview') }}
        </x-admin.nav-link>

        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_catalog') }}
        </p>
        <x-admin.nav-link :href="route('app.admin.producers.index')" matches="app.admin.producers.*">
            {{ __('Producers') }}
        </x-admin.nav-link>
        <x-admin.nav-link
            :href="route('app.admin.products.index')"
            :matches="['app.admin.products.index', 'app.admin.products.create', 'app.admin.products.edit']"
        >
            {{ __('Products') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.products.weekly.show')" matches="app.admin.products.weekly.*">
            {{ __('admin.weekly_catalog.nav_label') }}
        </x-admin.nav-link>

        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_operations') }}
        </p>
        <x-admin.nav-link :href="route('app.admin.users.index')" matches="app.admin.users.*">
            {{ __('admin.operations.nav_users') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.subscriptions.index')" matches="app.admin.subscriptions.*">
            {{ __('admin.operations.nav_subscriptions') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.orders.index', ['product_type' => 'trial_box'])" matches="app.admin.orders.*">
            {{ __('Trial orders') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.deliveries.index')" matches="app.admin.deliveries.*">
            {{ __('admin.operations.nav_deliveries') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.boxes.index')" matches="app.admin.boxes.*">
            {{ __('admin.operations.nav_boxes_setup') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.payments.index')" matches="app.admin.payments.*">
            {{ __('admin.operations.nav_payments') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.billing.upcoming')" matches="app.admin.billing.upcoming">
            {{ __('admin.operations.nav_upcoming') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.billing.coverage')" matches="app.admin.billing.coverage">
            {{ __('admin.operations.nav_billing_coverage') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.audit-events.index')" matches="app.admin.audit-events.*">
            {{ __('admin.audit.nav_label') }}
        </x-admin.nav-link>
        <x-admin.nav-link :href="route('app.admin.payment-diagnostics.index')" matches="app.admin.payment-diagnostics.*">
            {{ __('admin.operations.nav_payment_diagnostics') }}
        </x-admin.nav-link>
    </nav>

    <div class="border-t border-amber-100 p-3">
        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_account') }}
        </p>
        <x-admin.nav-link :href="route('account.profile.edit')" matches="account.profile.*">
            {{ __('Account settings') }}
        </x-admin.nav-link>
        <form method="POST" action="{{ route('auth.logout') }}" class="mt-1">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm font-medium text-gray-700 transition hover:bg-amber-50 hover:text-amber-900"
            >
                {{ __('Log Out') }}
            </button>
        </form>
        <div class="mt-3 px-3">
            <x-language-selector align="left" />
        </div>
    </div>
</aside>
