<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://gateway.sumup.com; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; frame-src https://gateway.sumup.com; connect-src 'self' https://gateway.sumup.com https://api.sumup.com https://js.sumup.com; img-src 'self' data: https:">
        <title>{{ config('app.name', 'Erdgut') }} — {{ $title ?? __('Get started') }}</title>
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/erdgut-favicon-192.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <div class="min-h-screen flex flex-col" data-testid="onboarding-layout">
            <header class="border-b border-brand-ink/10 bg-brand-paper">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 py-5 flex items-center justify-between">
                    <a href="{{ route('public.home') }}" class="flex items-center gap-3">
                        <x-application-logo variant="filled" :withWordmark="false" class="h-14 w-auto" />
                        <span class="flex flex-col leading-tight">
                            <span class="eyebrow text-chapter-produce">{{ __('A seat at the table') }}</span>
                            <span class="font-display text-base font-semibold text-brand-ink">{{ config('app.name', 'Erdgut') }}</span>
                        </span>
                    </a>
                    <div class="flex items-center gap-4">
                        <x-language-selector />
                        @auth
                            <form method="POST" action="{{ route('auth.logout') }}">@csrf
                                <button type="submit" class="text-sm text-brand-ink/60 hover:text-brand-ink underline-offset-4 hover:underline">{{ __('Log out') }}</button>
                            </form>
                        @else
                            <a href="{{ route('auth.login') }}" class="text-sm text-brand-ink/60 hover:text-brand-ink underline-offset-4 hover:underline">{{ __('Log in') }}</a>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="flex-1">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 grid gap-8 lg:grid-cols-[1fr_320px]">
                    <div>
                        @isset($step)
                            <x-onboarding.progress :step="$step" :intent="$intent ?? 'undecided'" />
                        @endisset

                        <div class="mt-6 border border-brand-ink/15 bg-white p-6 sm:p-8">
                            @yield('content')
                        </div>
                    </div>

                    <aside class="lg:pl-4">
                        <div class="border-t-2 border-chapter-produce bg-white/70 p-5 sticky top-6">
                            <p class="eyebrow text-chapter-produce">{{ __('Your box') }}</p>
                            <dl class="mt-3 text-sm space-y-2 text-brand-ink/80">
                                @isset($draft)
                                    @php($data = $draft->data ?? [])
                                    <div class="flex justify-between">
                                        <dt>{{ __('Size') }}</dt>
                                        <dd class="font-medium">{{ isset($data['box_size']) ? __(ucfirst($data['box_size'])) : '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>{{ __('Intent') }}</dt>
                                        <dd class="font-medium">{{ ucfirst($draft->intent) }}</dd>
                                    </div>
                                    @if(!empty($data['preferences']))
                                        <div>
                                            <dt class="mb-1">{{ __('Preferences') }}</dt>
                                            <dd class="text-xs text-brand-ink/70">
                                                @foreach($data['preferences'] as $k => $v)
                                                    @if($v)<span class="inline-block px-2 py-0.5 mr-1 mb-1 bg-chapter-produce/10 text-chapter-produce">{{ str_replace('_',' ',$k) }}</span>@endif
                                                @endforeach
                                            </dd>
                                        </div>
                                    @endif
                                    @if(!empty($data['allergies']))
                                        <div>
                                            <dt class="mb-1">{{ __('Allergies') }}</dt>
                                            <dd class="text-xs text-brand-ink/70">{{ implode(', ', $data['allergies']) }}</dd>
                                        </div>
                                    @endif
                                @endisset
                            </dl>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </body>
</html>
