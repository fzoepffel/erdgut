<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantVerificationStatus;
use App\Http\Controllers\Controller;
use App\Mail\MercurialMail;
use App\Models\Mercurial;
use App\Models\MercurialRecipient;
use App\Models\User;
use App\Support\MercurialHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly restaurant "Mercurial": a WYSIWYG composer that sends a personalized
 * offer email to hand-picked restaurant customers (and any extra addresses the
 * admin keeps in a small contact book). One queued email per recipient.
 *
 * The mercurial is bilingual: a German and an English version (each with its
 * own subject + body) are composed together, and every recipient receives the
 * version matching their persisted language (de/en).
 */
class AdminMercurialController extends Controller
{
    public function index(Request $request): View
    {
        $prefill = null;
        if ($request->filled('duplicate')) {
            $prefill = Mercurial::query()->find((int) $request->query('duplicate'));
        }

        $week = str_pad((string) now()->isoWeek, 2, '0', STR_PAD_LEFT);

        return view('admin.mercurial.index', [
            'restaurants' => $this->restaurantCandidates(),
            'contacts' => MercurialRecipient::query()->orderBy('restaurant')->orderBy('name')->orderBy('email')->get(),
            'history' => Mercurial::query()->latest('sent_at')->limit(25)->get(),
            'prefillSubjectDe' => $prefill?->subject ?? $this->defaultSubject('de', $week),
            'prefillSubjectEn' => $prefill?->subject_en ?? $this->defaultSubject('en', $week),
            'prefillBodyDe' => $prefill?->body_html ?? '',
            'prefillBodyEn' => $prefill?->body_html_en ?? '',
        ]);
    }

    /** Add a reusable contact (an address that is not a restaurant account). */
    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'restaurant' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'in:de,en'],
        ]);

        MercurialRecipient::query()->updateOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            [
                'name' => $data['name'] ?? null,
                'restaurant' => $data['restaurant'] ?? null,
                'locale' => $data['locale'] ?? 'de',
            ],
        );

        return redirect()
            ->route('app.admin.mercurial.index')
            ->with('status', __('mercurial.contact_added'));
    }

    public function destroyContact(MercurialRecipient $recipient): RedirectResponse
    {
        $recipient->delete();

        return redirect()
            ->route('app.admin.mercurial.index')
            ->with('status', __('mercurial.contact_removed'));
    }

    /** Persist a recipient's language (de/en). Called from the inline toggle. */
    public function setRecipientLocale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'locale' => ['required', 'in:de,en'],
        ]);

        $key = mb_strtolower(trim($data['email']));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$key])
            ->whereHas('restaurantProfile', fn ($q) => $q->where('verification_status', RestaurantVerificationStatus::Verified))
            ->with('restaurantProfile')
            ->first();

        if ($user?->restaurantProfile !== null) {
            // Mercurial-specific: does not touch the account/shop language.
            $user->restaurantProfile->update(['mercurial_locale' => $data['locale']]);
        } else {
            MercurialRecipient::query()->updateOrCreate(['email' => $key], ['locale' => $data['locale']]);
        }

        return response()->json(['ok' => true, 'locale' => $data['locale']]);
    }

    /** Send a preview of each filled language version to the signed-in admin. */
    public function test(Request $request): RedirectResponse
    {
        $data = $this->validateContent($request);
        $admin = $request->user();
        $versions = $this->versions($data);

        if ($versions === []) {
            return back()->withErrors(['body_de' => __('mercurial.error_no_content')])->withInput();
        }

        foreach ($versions as $version) {
            Mail::to($admin->email)->sendNow(new MercurialMail(
                $this->personalize($version['subject'], $admin?->name, __('mercurial.sample_restaurant')),
                $this->personalize($version['body'], $admin?->name, __('mercurial.sample_restaurant')),
            ));
        }

        return redirect()
            ->route('app.admin.mercurial.index')
            ->with('status', __('mercurial.test_sent', ['email' => $admin->email]));
    }

    /** Personalize + queue one email per recipient in their language; log it. */
    public function send(Request $request): RedirectResponse
    {
        $data = $this->validateContent($request, requireRecipients: true);
        $versions = $this->versions($data);

        $adhocNames = (array) $request->input('adhoc_names', []);
        $adhocRestaurants = (array) $request->input('adhoc_restaurants', []);
        $adhocLocales = (array) $request->input('adhoc_locales', []);
        $known = $this->directory();

        $recipients = [];
        foreach ($this->normalizeEmails($data['recipients']) as $email) {
            $recipients[] = $this->resolveRecipient($email, $known, $adhocNames, $adhocRestaurants, $adhocLocales);
        }

        // Every language actually in use must have a subject + body filled in.
        $neededLocales = collect($recipients)->pluck('locale')->unique();
        foreach ($neededLocales as $locale) {
            if (! isset($versions[$locale])) {
                return back()
                    ->withErrors(['body_'.$locale => __('mercurial.error_missing_'.$locale)])
                    ->withInput();
            }
        }

        $sent = [];
        foreach ($recipients as $r) {
            $version = $versions[$r['locale']];

            Mail::to($r['email'])->queue(new MercurialMail(
                $this->personalize($version['subject'], $r['name'], $r['restaurant']),
                $this->personalize($version['body'], $r['name'], $r['restaurant']),
            ));

            $sent[] = $r;
        }

        Mercurial::query()->create([
            'subject' => $data['subject_de'] ?? '',
            'subject_en' => $data['subject_en'] ?? null,
            'body_html' => $versions['de']['body'] ?? '',
            'body_html_en' => $versions['en']['body'] ?? null,
            'recipients' => $sent,
            'recipient_count' => count($sent),
            'created_by_user_id' => $request->user()?->id,
            'sent_at' => now(),
        ]);

        return redirect()
            ->route('app.admin.mercurial.index')
            ->with('status', __('mercurial.sent', ['count' => count($sent)]));
    }

    /**
     * @return array{subject_de: ?string, body_de: ?string, subject_en: ?string, body_en: ?string, recipients?: array<int, string>}
     */
    private function validateContent(Request $request, bool $requireRecipients = false): array
    {
        $rules = [
            'subject_de' => ['nullable', 'string', 'max:255'],
            'body_de' => ['nullable', 'string', 'max:100000'],
            'subject_en' => ['nullable', 'string', 'max:255'],
            'body_en' => ['nullable', 'string', 'max:100000'],
        ];
        if ($requireRecipients) {
            $rules['recipients'] = ['required', 'array', 'min:1'];
            $rules['recipients.*'] = ['email'];
        }

        return $request->validate($rules);
    }

    /**
     * Language versions that are fully filled in (subject AND body), keyed by
     * locale, with the body sanitized.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{subject: string, body: string}>
     */
    private function versions(array $data): array
    {
        $versions = [];
        foreach (['de', 'en'] as $locale) {
            $subject = trim((string) ($data['subject_'.$locale] ?? ''));
            $body = trim((string) ($data['body_'.$locale] ?? ''));
            if ($subject !== '' && $body !== '') {
                $versions[$locale] = [
                    'subject' => $subject,
                    'body' => MercurialHtml::sanitize($body),
                ];
            }
        }

        return $versions;
    }

    private function defaultSubject(string $locale, string $week): string
    {
        return __('mercurial.default_subject', ['week' => $week], $locale);
    }

    /**
     * Verified restaurant accounts as recipient candidates, each with the
     * language the mercurial should go out in.
     *
     * @return Collection<int, array{email: string, name: ?string, restaurant: ?string, locale: string}>
     */
    private function restaurantCandidates(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->whereHas('restaurantProfile', fn ($q) => $q->where('verification_status', RestaurantVerificationStatus::Verified))
            ->with('restaurantProfile')
            ->get()
            ->map(fn (User $u): array => [
                'email' => (string) $u->email,
                'name' => $u->restaurantProfile?->contact_name,
                'restaurant' => $u->restaurantProfile?->business_name,
                'locale' => $this->normalizeLocale($u->restaurantProfile?->mercurial_locale ?: $u->locale),
            ])
            ->sortBy('restaurant')
            ->values();
    }

    /**
     * email => [name, restaurant, locale] for every known recipient.
     *
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    private function directory(): array
    {
        $map = [];
        foreach ($this->restaurantCandidates() as $r) {
            $map[mb_strtolower($r['email'])] = [$r['name'], $r['restaurant'], $r['locale']];
        }
        foreach (MercurialRecipient::query()->get() as $c) {
            $key = mb_strtolower((string) $c->email);
            $map[$key] ??= [$c->name, $c->restaurant, $this->normalizeLocale($c->locale)];
        }

        return $map;
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string, 2: string}>  $known
     * @param  array<string, mixed>  $adhocNames
     * @param  array<string, mixed>  $adhocRestaurants
     * @param  array<string, mixed>  $adhocLocales
     * @return array{email: string, name: ?string, restaurant: ?string, locale: string}
     */
    private function resolveRecipient(string $email, array $known, array $adhocNames, array $adhocRestaurants, array $adhocLocales): array
    {
        $key = mb_strtolower($email);
        if (isset($known[$key])) {
            [$name, $restaurant, $locale] = $known[$key];

            return ['email' => $key, 'name' => $name, 'restaurant' => $restaurant, 'locale' => $locale];
        }

        // A hand-typed address: use any name/restaurant/language given, and
        // remember it for next time so it does not have to be re-entered.
        $name = isset($adhocNames[$email]) ? trim((string) $adhocNames[$email]) : '';
        $name = $name !== '' ? $name : null;
        $restaurant = isset($adhocRestaurants[$email]) ? trim((string) $adhocRestaurants[$email]) : '';
        $restaurant = $restaurant !== '' ? $restaurant : null;
        $locale = $this->normalizeLocale($adhocLocales[$email] ?? null);

        MercurialRecipient::query()->firstOrCreate(
            ['email' => $key],
            ['name' => $name, 'restaurant' => $restaurant, 'locale' => $locale],
        );

        return ['email' => $key, 'name' => $name, 'restaurant' => $restaurant, 'locale' => $locale];
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    private function normalizeEmails(array $emails): array
    {
        return collect($emails)
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Only German and English are offered; anything else falls back to German. */
    private function normalizeLocale(?string $locale): string
    {
        return $locale === 'en' ? 'en' : 'de';
    }

    private function personalize(string $text, ?string $name, ?string $restaurant): string
    {
        $name = ($name !== null && trim($name) !== '') ? trim($name) : (string) config('mercurial.fallback_name');
        $restaurant = ($restaurant !== null && trim($restaurant) !== '') ? trim($restaurant) : (string) config('mercurial.fallback_restaurant');

        return strtr($text, [
            '{{name}}' => $name,
            '{{ name }}' => $name,
            '{{restaurant}}' => $restaurant,
            '{{ restaurant }}' => $restaurant,
        ]);
    }
}
