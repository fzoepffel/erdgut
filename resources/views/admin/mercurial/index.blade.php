<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('mercurial.title') }}</h1>
            <span class="text-sm text-gray-500">{{ __('mercurial.subtitle') }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Composer ─────────────────────────────────────────────── --}}
            <form method="POST" action="{{ route('app.admin.mercurial.send') }}" class="space-y-6">
                @csrf

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <label for="mercurial-subject" class="block text-sm font-medium text-gray-700">{{ __('mercurial.field_subject') }}</label>
                    <input id="mercurial-subject" name="subject" type="text" required maxlength="255"
                        value="{{ old('subject', $prefillSubject) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">

                    <label class="mt-5 block text-sm font-medium text-gray-700">{{ __('mercurial.field_body') }}</label>
                    <input id="mercurial-body" type="hidden" name="body_html" value="{{ old('body_html', $prefillBody) }}">
                    <trix-editor input="mercurial-body" class="mercurial-editor mt-1 block w-full rounded-md border border-gray-300 bg-white"></trix-editor>

                    <p class="mt-3 text-xs text-gray-500">
                        {{ __('mercurial.tokens_help') }}
                        <code class="rounded bg-gray-100 px-1">&#123;&#123;name&#125;&#125;</code>
                        <code class="rounded bg-gray-100 px-1">&#123;&#123;restaurant&#125;&#125;</code>
                    </p>
                </div>

                {{-- ── Recipients ───────────────────────────────────────── --}}
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"
                    x-data="{ extra: [], email: '', name: '', add() { const e = this.email.trim().toLowerCase(); if (!e) return; if (!this.extra.some(r => r.email === e)) this.extra.push({ email: e, name: this.name.trim() }); this.email = ''; this.name = ''; } }">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-medium text-gray-700">{{ __('mercurial.recipients_title') }}</h2>
                        <span class="text-xs text-gray-400">{{ __('mercurial.recipients_hint') }}</span>
                    </div>

                    @if ($restaurants->isNotEmpty())
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('mercurial.group_restaurants') }}</p>
                        <div class="mt-2 grid gap-1 sm:grid-cols-2">
                            @foreach ($restaurants as $r)
                                <label class="flex items-start gap-2 rounded px-2 py-1 hover:bg-gray-50">
                                    <input type="checkbox" name="recipients[]" value="{{ $r['email'] }}" checked
                                        class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">
                                        <span class="font-medium">{{ $r['restaurant'] ?: $r['name'] ?: $r['email'] }}</span>
                                        <span class="block text-xs text-gray-400">{{ $r['name'] ? $r['name'].' · ' : '' }}{{ $r['email'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    @if ($contacts->isNotEmpty())
                        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('mercurial.group_contacts') }}</p>
                        <div class="mt-2 grid gap-1 sm:grid-cols-2">
                            @foreach ($contacts as $c)
                                <label class="flex items-start gap-2 rounded px-2 py-1 hover:bg-gray-50">
                                    <input type="checkbox" name="recipients[]" value="{{ $c->email }}" checked
                                        class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">
                                        <span class="font-medium">{{ $c->name ?: $c->email }}</span>
                                        @if ($c->name)<span class="block text-xs text-gray-400">{{ $c->email }}</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    {{-- One-off addresses added just for this send (auto-saved to the address book). --}}
                    <template x-for="(r, i) in extra" :key="r.email">
                        <label class="mt-1 flex items-center gap-2 rounded px-2 py-1">
                            <input type="checkbox" name="recipients[]" :value="r.email" checked
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <input type="hidden" :name="'adhoc_names[' + r.email + ']'" :value="r.name">
                            <span class="text-sm text-gray-700"><span x-text="r.email"></span><span x-show="r.name" class="text-gray-400"> · <span x-text="r.name"></span></span></span>
                            <button type="button" @click="extra.splice(i, 1)" class="text-xs text-red-500 hover:text-red-700">{{ __('mercurial.remove') }}</button>
                        </label>
                    </template>

                    <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-4">
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('mercurial.add_email') }}</label>
                            <input type="email" x-model="email" @keydown.enter.prevent="add()" placeholder="name@restaurant.de"
                                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('mercurial.add_name') }}</label>
                            <input type="text" x-model="name" @keydown.enter.prevent="add()"
                                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="button" @click="add()" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('mercurial.add_button') }}</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" onclick="return confirm(@json(__('mercurial.confirm_send')))"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">{{ __('mercurial.send_button') }}</button>
                    <button type="submit" formaction="{{ route('app.admin.mercurial.test') }}" formnovalidate
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('mercurial.test_button') }}</button>
                    <span class="text-xs text-gray-400">{{ __('mercurial.test_hint') }}</span>
                </div>
            </form>

            {{-- ── Address book (persistent contacts) ───────────────────── --}}
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-medium text-gray-700">{{ __('mercurial.address_book') }}</h2>
                <form method="POST" action="{{ route('app.admin.mercurial.contacts.store') }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('mercurial.add_email') }}</label>
                        <input type="email" name="email" required placeholder="name@restaurant.de"
                            class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('mercurial.add_name') }}</label>
                        <input type="text" name="name"
                            class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('mercurial.save_contact') }}</button>
                </form>

                @if ($contacts->isNotEmpty())
                    <ul class="mt-4 divide-y divide-gray-100 text-sm">
                        @foreach ($contacts as $c)
                            <li class="flex items-center justify-between py-2">
                                <span class="text-gray-700">{{ $c->name ? $c->name.' · ' : '' }}{{ $c->email }}</span>
                                <form method="POST" action="{{ route('app.admin.mercurial.contacts.destroy', $c) }}"
                                    onsubmit="return confirm(@json(__('mercurial.confirm_remove_contact')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">{{ __('mercurial.remove') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- ── History ──────────────────────────────────────────────── --}}
            @if ($history->isNotEmpty())
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-medium text-gray-700">{{ __('mercurial.history_title') }}</h2>
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        @foreach ($history as $m)
                            <li class="flex items-center justify-between py-2">
                                <span class="text-gray-700">
                                    <span class="font-medium">{{ $m->subject }}</span>
                                    <span class="block text-xs text-gray-400">{{ $m->sent_at?->format('d.m.Y H:i') }} · {{ __('mercurial.history_count', ['count' => $m->recipient_count]) }}</span>
                                </span>
                                <a href="{{ route('app.admin.mercurial.index', ['duplicate' => $m->id]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">{{ __('mercurial.reuse') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/mercurial.js')
    @endpush
</x-admin-layout>
