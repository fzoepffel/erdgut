@php
    $isOperator = Auth::user()?->isAdmin() ?? false;
    $hasSubscriberNav = $hasSubscriberNav ?? false;

    // W2-01: app nav inherits the same chapter-produce green visual as
    // the marketing header so customers see one consistent brand chrome.
    // Inline styles match `<x-customer-header>` exactly.
    $headerBg = '#709452';
    $headerInk = '#21341d';
    $headerInkSubtle = 'rgba(33, 52, 29, 0.75)';
@endphp
<nav x-data="{ mobileMenuOpen: false }" class="relative z-50 border-b" style="background-color: {{ $headerBg }}; border-color: rgba(33, 52, 29, 0.15);" data-testid="customer-header-app">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between">
            <div class="flex items-center gap-10">
                <a href="{{ $isOperator ? route('app.admin.dashboard') : route('app.dashboard') }}" class="flex items-center gap-3">
                    <span
                        aria-hidden="true"
                        class="block h-10 w-10 shrink-0 sm:h-11 sm:w-11"
                        style="background-color: {{ $headerInk }}; -webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"
                    ></span>
                    <span
                        role="img"
                        aria-label="Erdgut"
                        class="block h-8 w-40 sm:h-9 sm:w-44"
                        style="background-color: {{ $headerInk }}; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
                    ></span>
                </a>

                <div class="hidden items-center gap-8 sm:flex text-sm font-medium">
                    @php
                        $linkStyle = fn (bool $active) => 'color: '.($active ? $headerInk : $headerInkSubtle).';';
                    @endphp
                    @if($isOperator)
                        @php($active = request()->routeIs('app.admin.*'))
                        <a href="{{ route('app.admin.dashboard') }}" @if ($active) aria-current="page" @endif class="transition {{ $active ? 'font-semibold' : '' }}" style="{{ $linkStyle($active) }}">
                            {{ __('admin.shell.overview') }}
                        </a>
                    @else
                        @php($active = request()->routeIs('app.dashboard'))
                        <a href="{{ route('app.dashboard') }}" @if ($active) aria-current="page" @endif class="transition {{ $active ? 'font-semibold' : '' }}" style="{{ $linkStyle($active) }}">
                            {{ __('Dashboard') }}
                        </a>
                        @if ($hasSubscriberNav)
                            @php($active = request()->routeIs('app.subscriptions.*'))
                            <a href="{{ route('app.subscriptions.show') }}" @if ($active) aria-current="page" @endif class="transition {{ $active ? 'font-semibold' : '' }}" style="{{ $linkStyle($active) }}">
                                {{ __('Subscription') }}
                            </a>
                            @php($active = request()->routeIs('app.deliveries.*') && !request()->routeIs('app.deliveries.preferences.*'))
                            <a href="{{ route('app.deliveries.index') }}" @if ($active) aria-current="page" @endif class="transition {{ $active ? 'font-semibold' : '' }}" style="{{ $linkStyle($active) }}">
                                {{ __('Deliveries') }}
                            </a>
                        @endif
                        @php($active = request()->routeIs('app.products.*'))
                        <a href="{{ route('app.products.index') }}" @if ($active) aria-current="page" @endif class="transition {{ $active ? 'font-semibold' : '' }}" style="{{ $linkStyle($active) }}">
                            {{ __('products.index_title') }}
                        </a>
                        @can('admin')
                            @php($active = request()->routeIs('app.admin.*'))
                            <a href="{{ route('app.admin.dashboard') }}" @if ($active) aria-current="page" @endif class="transition {{ $active ? 'font-semibold' : '' }}" style="{{ $linkStyle($active) }}">
                                {{ __('Admin') }}
                            </a>
                        @endcan
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-2" style="color: {{ $headerInk }};">
                <x-language-selector />
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition duration-200 focus:outline-none" style="color: {{ $headerInk }};">
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

                        <form method="POST" action="{{ route('auth.logout') }}" data-purpose="logout">
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
                <button type="button" @click="mobileMenuOpen = ! mobileMenuOpen" class="inline-flex items-center justify-center p-2 transition duration-200 focus:outline-none" style="color: {{ $headerInk }};" :aria-expanded="mobileMenuOpen">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileMenuOpen, 'inline-flex': ! mobileMenuOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! mobileMenuOpen, 'inline-flex': mobileMenuOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': mobileMenuOpen, 'hidden': ! mobileMenuOpen}" class="hidden border-t sm:hidden" style="border-color: rgba(33, 52, 29, 0.15);">
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

                <form method="POST" action="{{ route('auth.logout') }}" data-purpose="logout">
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
