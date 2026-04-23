@php
    $isOperator = Auth::user()?->isAdmin() ?? false;
    $hasSubscriberNav = $hasSubscriberNav ?? false;
@endphp
<nav x-data="{ mobileMenuOpen: false }" class="relative z-50 border-b border-brand-ink/10 bg-brand-cream/95 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between">
            <div class="flex items-center gap-10">
                <a href="{{ $isOperator ? route('app.admin.dashboard') : route('app.dashboard') }}" class="flex items-center">
                    <x-application-logo variant="filled" class="block h-14 w-auto" />
                </a>

                <div class="hidden items-center gap-8 sm:flex">
                    @if($isOperator)
                        <x-nav-link :href="route('app.admin.dashboard')" :active="request()->routeIs('app.admin.*')">
                            {{ __('admin.shell.overview') }}
                        </x-nav-link>
                    @else
                        <x-nav-link :href="route('app.dashboard')" :active="request()->routeIs('app.dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        @if ($hasSubscriberNav)
                            <x-nav-link :href="route('app.subscriptions.show')" :active="request()->routeIs('app.subscriptions.*')">
                                {{ __('Subscription') }}
                            </x-nav-link>
                            <x-nav-link :href="route('app.deliveries.index')" :active="request()->routeIs('app.deliveries.*') && !request()->routeIs('app.deliveries.preferences.*')">
                                {{ __('Deliveries') }}
                            </x-nav-link>
                        @endif
                        <x-nav-link :href="route('app.products.index')" :active="request()->routeIs('app.products.*')">
                            {{ __('products.index_title') }}
                        </x-nav-link>
                        @can('admin')
                            <x-nav-link :href="route('app.admin.dashboard')" :active="request()->routeIs('app.admin.*')">
                                {{ __('Admin') }}
                            </x-nav-link>
                        @endcan
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-2">
                <x-language-selector />
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-brand-ink/80 transition duration-200 hover:text-brand-ink focus:outline-none">
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @unless($isOperator)
                            @can('admin')
                                <x-dropdown-link :href="route('app.admin.dashboard')">
                                    {{ __('Admin') }}
                                </x-dropdown-link>
                            @endcan
                            @if ($hasSubscriberNav)
                                <x-dropdown-link :href="route('app.payments.history')">
                                    {{ __('Payments') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('app.deliveries.preferences.edit')">
                                    {{ __('Delivery preferences') }}
                                </x-dropdown-link>
                            @endif
                        @endunless
                        <x-dropdown-link :href="route('account.profile.edit')">
                            {{ __('Account settings') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('auth.logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('auth.logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button type="button" @click="mobileMenuOpen = ! mobileMenuOpen" class="inline-flex items-center justify-center p-2 text-brand-ink transition duration-200 hover:bg-brand-ink/5 focus:outline-none" :aria-expanded="mobileMenuOpen">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileMenuOpen, 'inline-flex': ! mobileMenuOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! mobileMenuOpen, 'inline-flex': mobileMenuOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': mobileMenuOpen, 'hidden': ! mobileMenuOpen}" class="hidden border-t border-brand-ink/10 sm:hidden">
        <div class="space-y-1 py-3">
            @if($isOperator)
                <x-responsive-nav-link :href="route('app.admin.dashboard')" :active="request()->routeIs('app.admin.*')">
                    {{ __('admin.shell.overview') }}
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('app.dashboard')" :active="request()->routeIs('app.dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                @if ($hasSubscriberNav)
                    <x-responsive-nav-link :href="route('app.subscriptions.show')" :active="request()->routeIs('app.subscriptions.*')">
                        {{ __('Subscription') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('app.deliveries.index')" :active="request()->routeIs('app.deliveries.*') && !request()->routeIs('app.deliveries.preferences.*')">
                        {{ __('Deliveries') }}
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('app.products.index')" :active="request()->routeIs('app.products.*')">
                    {{ __('products.index_title') }}
                </x-responsive-nav-link>
                @can('admin')
                    <x-responsive-nav-link :href="route('app.admin.dashboard')" :active="request()->routeIs('app.admin.*')">
                        {{ __('Admin') }}
                    </x-responsive-nav-link>
                @endcan
            @endif
        </div>

        <div class="border-t border-brand-ink/10 py-3">
            <div class="px-4 pb-3">
                <div class="text-base font-medium text-brand-ink">{{ Auth::user()->name }}</div>
                <div class="text-sm text-brand-ink/60">{{ Auth::user()->email }}</div>
            </div>

            <div class="space-y-1">
                @unless($isOperator)
                    @if ($hasSubscriberNav)
                        <x-responsive-nav-link :href="route('app.payments.history')">
                            {{ __('Payments') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('app.deliveries.preferences.edit')">
                            {{ __('Delivery preferences') }}
                        </x-responsive-nav-link>
                    @endif
                @endunless
                <x-responsive-nav-link :href="route('account.profile.edit')">
                    {{ __('Account settings') }}
                </x-responsive-nav-link>

                <div class="px-4 py-2">
                    <x-language-selector align="left" />
                </div>

                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('auth.logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
