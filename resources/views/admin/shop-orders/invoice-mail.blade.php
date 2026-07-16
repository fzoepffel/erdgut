<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ $backUrl }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('restaurant.invoice_editor_back') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.invoice_mail_heading', ['number' => $order->invoice_number]) }}</h1>
        </div>
    </x-slot>

    @php
        $inp = 'w-full rounded-none border-0 border-b border-dashed border-transparent bg-transparent p-0 text-[13px] leading-relaxed text-gray-900 placeholder:text-gray-300 hover:border-gray-300 focus:border-emerald-500 focus:ring-0';
    @endphp

    {{-- Single-quoted x-data: templates are json_encode output (double-quoted
         strings); JSON_HEX_APOS keeps apostrophes from closing the quotes. --}}
    <div class="py-8"
         x-data='invoiceMail({
             templates: {!! json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!}
         })'>
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                <button type="submit" form="invoice-mail-form" class="btn-solid-sm" data-testid="invoice-mail-send">
                    {{ __('restaurant.invoice_mail_send') }}
                </button>
                {{-- Language toggle: swaps subject + body to the chosen base
                     text (overwrites current edits, the hint says so). --}}
                <div class="ml-2 flex items-center gap-1 rounded-md border border-gray-200 p-0.5" role="group">
                    <button type="button" x-on:click="applyTemplate('de')"
                            :class="lang === 'de' ? 'bg-emerald-700 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            class="rounded px-2.5 py-1 text-xs font-semibold" data-testid="invoice-mail-lang-de">Deutsch</button>
                    <button type="button" x-on:click="applyTemplate('en')"
                            :class="lang === 'en' ? 'bg-emerald-700 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            class="rounded px-2.5 py-1 text-xs font-semibold" data-testid="invoice-mail-lang-en">English</button>
                </div>
                <a href="{{ $backUrl }}" class="ml-auto text-sm text-gray-500 hover:text-gray-700">
                    {{ __('restaurant.invoice_mail_skip') }}
                </a>
            </div>
            <p class="px-1 text-xs text-gray-400">{{ __('restaurant.invoice_mail_hint') }}</p>

            {{-- ── The email, editable like a draft in a mail client ── --}}
            <form id="invoice-mail-form" method="POST" action="{{ route('app.admin.shop-orders.invoice-mail.send', $order) }}">
                @csrf
                <div class="bg-white px-8 py-8 shadow-md sm:px-10" style="font-size: 13px; line-height: 1.5;">
                    <div class="space-y-2 border-b border-gray-200 pb-4">
                        <div class="flex items-baseline gap-2">
                            <span class="w-20 shrink-0 text-[12px] font-semibold uppercase tracking-wide text-gray-400">{{ __('restaurant.invoice_mail_to') }}</span>
                            <input type="text" name="recipients" value="{{ old('recipients', $recipients) }}"
                                   class="{{ $inp }}" data-testid="invoice-mail-recipients" required>
                        </div>
                        <p class="pl-[5.5rem] text-[11px] text-gray-400">{{ __('restaurant.invoice_mail_to_hint') }}</p>
                        <div class="flex items-baseline gap-2">
                            <span class="w-20 shrink-0 text-[12px] font-semibold uppercase tracking-wide text-gray-400">{{ __('restaurant.invoice_mail_subject') }}</span>
                            <input type="text" name="subject" x-model="subject"
                                   class="{{ $inp }} font-semibold" data-testid="invoice-mail-subject" required>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="w-20 shrink-0 text-[12px] font-semibold uppercase tracking-wide text-gray-400">{{ __('restaurant.invoice_mail_attachment') }}</span>
                            <span class="text-[12px] text-gray-600">erdgut-rechnung-{{ $order->invoice_number }}.pdf</span>
                        </div>
                    </div>

                    <textarea name="body" x-model="body" rows="14"
                              class="mt-4 w-full resize-y rounded-none border-0 bg-transparent p-0 text-[13px] leading-relaxed text-gray-900 focus:ring-0"
                              data-testid="invoice-mail-body" required></textarea>
                </div>
            </form>
        </div>
    </div>

    <script>
    function invoiceMail(config) {
        var templates = config.templates || {};
        var initialSubject = {!! json_encode(old('subject', ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!};
        var initialBody = {!! json_encode(old('body', ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!};

        return {
            lang: "de",
            subject: initialSubject !== "" ? initialSubject : (templates.de ? templates.de.subject : ""),
            body: initialBody !== "" ? initialBody : (templates.de ? templates.de.body : ""),

            applyTemplate(lang) {
                var tpl = templates[lang];
                if (!tpl) return;
                this.lang = lang;
                this.subject = tpl.subject;
                this.body = tpl.body;
            },
        };
    }
    </script>
</x-admin-layout>
