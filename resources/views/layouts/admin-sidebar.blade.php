{{-- Persistent backoffice sidebar; parent layout must wrap with x-data="{ adminSidebarOpen: false }". --}}
@php
    $navLinkBase = 'flex items-center rounded-md px-3 py-2 text-sm font-medium transition';
    $navInactive = 'text-gray-700 hover:bg-amber-50 hover:text-amber-900';
    $navActive = 'bg-amber-100 text-amber-900';
@endphp

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
    <div class="flex h-16 shrink-0 items-center border-b border-amber-100 px-4">
        <a
            href="{{ route('app.admin.dashboard') }}"
            class="flex items-center gap-2 text-amber-950"
            @click="adminSidebarOpen = false"
        >
            <x-application-logo class="block h-8 w-auto fill-current text-amber-800" />
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
        <a
            href="{{ route('app.admin.dashboard') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.dashboard') ? $navActive : $navInactive }}"
        >
            {{ __('admin.shell.overview') }}
        </a>

        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_catalog') }}
        </p>
        <a
            href="{{ route('app.admin.producers.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.producers.*') ? $navActive : $navInactive }}"
        >
            {{ __('Producers') }}
        </a>
        <a
            href="{{ route('app.admin.products.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.products.index') || request()->routeIs('app.admin.products.create') || request()->routeIs('app.admin.products.edit') ? $navActive : $navInactive }}"
        >
            {{ __('Products') }}
        </a>
        <a
            href="{{ route('app.admin.products.weekly.show') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.products.weekly.*') ? $navActive : $navInactive }}"
        >
            {{ __('admin.weekly_catalog.nav_label') }}
        </a>

        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_operations') }}
        </p>
        <a
            href="{{ route('app.admin.users.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.users.*') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_users') }}
        </a>
        <a
            href="{{ route('app.admin.subscriptions.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.subscriptions.*') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_subscriptions') }}
        </a>
        <a
            href="{{ route('app.admin.orders.index', ['product_type' => 'trial_box']) }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.orders.*') ? $navActive : $navInactive }}"
        >
            {{ __('Trial orders') }}
        </a>
        <a
            href="{{ route('app.admin.deliveries.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.deliveries.*') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_deliveries') }}
        </a>
        <a
            href="{{ route('app.admin.boxes.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.boxes.*') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_boxes_setup') }}
        </a>
        <a
            href="{{ route('app.admin.payments.index') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.payments.*') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_payments') }}
        </a>
        <a
            href="{{ route('app.admin.billing.upcoming') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.billing.upcoming') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_upcoming') }}
        </a>
        <a
            href="{{ route('app.admin.billing.coverage') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('app.admin.billing.coverage') ? $navActive : $navInactive }}"
        >
            {{ __('admin.operations.nav_billing_coverage') }}
        </a>
    </nav>

    <div class="border-t border-amber-100 p-3">
        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-amber-800/80">
            {{ __('admin.shell.section_account') }}
        </p>
        <a
            href="{{ route('account.profile.edit') }}"
            @click="adminSidebarOpen = false"
            class="{{ $navLinkBase }} {{ request()->routeIs('account.profile.*') ? $navActive : $navInactive }}"
        >
            {{ __('Account settings') }}
        </a>
        <form method="POST" action="{{ route('auth.logout') }}" class="mt-1">
            @csrf
            <button
                type="submit"
                class="{{ $navLinkBase }} {{ $navInactive }} w-full text-left"
            >
                {{ __('Log Out') }}
            </button>
        </form>
        <div class="mt-3 px-3">
            <x-language-selector align="left" />
        </div>
    </div>
</aside>
