@props(['email', 'locale' => 'de'])

{{-- DE/EN language toggle for a recipient. Persists the choice immediately
     (POST to mercurial.recipient-locale) so it stays set for future sends.
     Fully inline Alpine so it does not depend on the mercurial JS bundle. --}}
<span class="shrink-0"
    x-data="{
        loc: @js($locale === 'en' ? 'en' : 'de'),
        busy: false,
        async set(l) {
            if (l === this.loc || this.busy) return;
            const prev = this.loc;
            this.loc = l;
            this.busy = true;
            try {
                const res = await fetch(@js(route('app.admin.mercurial.recipient-locale')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
                    body: JSON.stringify({ email: @js($email), locale: l }),
                });
                if (!res.ok) throw new Error('failed');
            } catch (e) {
                this.loc = prev;
            }
            this.busy = false;
        }
    }">
    <span class="inline-flex overflow-hidden rounded border border-gray-300 font-mono text-[0.65rem]" :class="busy ? 'opacity-60' : ''">
        <button type="button" @click="set('de')" :class="loc === 'de' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'" class="px-2 py-0.5">DE</button>
        <button type="button" @click="set('en')" :class="loc === 'en' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'" class="px-2 py-0.5">EN</button>
    </span>
</span>
