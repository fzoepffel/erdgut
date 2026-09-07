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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly restaurant "Mercurial": a WYSIWYG composer that sends a personalized
 * offer email to hand-picked restaurant customers (and any extra addresses the
 * admin keeps in a small contact book). One queued email per recipient.
 */
class AdminMercurialController extends Controller
{
    public function index(Request $request): View
    {
        $prefill = null;
        if ($request->filled('duplicate')) {
            $prefill = Mercurial::query()->find((int) $request->query('duplicate'));
        }

        return view('admin.mercurial.index', [
            'restaurants' => $this->restaurantCandidates(),
            'contacts' => MercurialRecipient::query()->orderBy('name')->orderBy('email')->get(),
            'history' => Mercurial::query()->latest('sent_at')->limit(25)->get(),
            'prefillSubject' => $prefill?->subject ?? __('mercurial.default_subject', [
                'week' => str_pad((string) now()->isoWeek, 2, '0', STR_PAD_LEFT),
            ]),
            'prefillBody' => $prefill?->body_html ?? '',
        ]);
    }

    /** Add a reusable contact (an address that is not a restaurant account). */
    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'restaurant' => ['nullable', 'string', 'max:255'],
        ]);

        MercurialRecipient::query()->updateOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            ['name' => $data['name'] ?? null, 'restaurant' => $data['restaurant'] ?? null],
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

    /** Send a single preview to the signed-in admin so they can proof it. */
    public function test(Request $request): RedirectResponse
    {
        $data = $this->validateContent($request);
        $admin = $request->user();

        $subject = $this->personalize($data['subject'], $admin?->name, __('mercurial.sample_restaurant'));
        $body = $this->personalize(MercurialHtml::sanitize($data['body_html']), $admin?->name, __('mercurial.sample_restaurant'));

        Mail::to($admin->email)->sendNow(new MercurialMail($subject, $body));

        return redirect()
            ->route('app.admin.mercurial.index')
            ->with('status', __('mercurial.test_sent', ['email' => $admin->email]));
    }

    /** Personalize + queue one email per selected recipient, and log the issue. */
    public function send(Request $request): RedirectResponse
    {
        $data = $this->validateContent($request, requireRecipients: true);

        $bodyTemplate = MercurialHtml::sanitize($data['body_html']);
        $adhocNames = (array) $request->input('adhoc_names', []);
        $adhocRestaurants = (array) $request->input('adhoc_restaurants', []);
        $known = $this->candidateMap();

        $sent = [];
        foreach ($this->normalizeEmails($data['recipients']) as $email) {
            [$name, $restaurant] = $this->resolveRecipient($email, $known, $adhocNames, $adhocRestaurants);

            Mail::to($email)->queue(new MercurialMail(
                $this->personalize($data['subject'], $name, $restaurant),
                $this->personalize($bodyTemplate, $name, $restaurant),
            ));

            $sent[] = ['email' => $email, 'name' => $name, 'restaurant' => $restaurant];
        }

        Mercurial::query()->create([
            'subject' => $data['subject'],
            'body_html' => $bodyTemplate,
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
     * @return array{subject: string, body_html: string, recipients?: array<int, string>}
     */
    private function validateContent(Request $request, bool $requireRecipients = false): array
    {
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:100000'],
        ];
        if ($requireRecipients) {
            $rules['recipients'] = ['required', 'array', 'min:1'];
            $rules['recipients.*'] = ['email'];
        }

        return $request->validate($rules);
    }

    /**
     * Verified restaurant accounts as recipient candidates.
     *
     * @return Collection<int, array{email: string, name: ?string, restaurant: ?string}>
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
            ])
            ->sortBy('restaurant')
            ->values();
    }

    /**
     * email => [name, restaurant] for every known recipient (restaurants + contacts).
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    private function candidateMap(): array
    {
        $map = [];
        foreach ($this->restaurantCandidates() as $r) {
            $map[mb_strtolower($r['email'])] = [$r['name'], $r['restaurant']];
        }
        foreach (MercurialRecipient::query()->get() as $c) {
            $key = mb_strtolower((string) $c->email);
            $map[$key] ??= [$c->name, $c->restaurant];
        }

        return $map;
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $known
     * @param  array<string, mixed>  $adhocNames
     * @param  array<string, mixed>  $adhocRestaurants
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveRecipient(string $email, array $known, array $adhocNames, array $adhocRestaurants): array
    {
        $key = mb_strtolower($email);
        if (isset($known[$key])) {
            return $known[$key];
        }

        // A hand-typed address: use any name/restaurant given, and remember it
        // for next time so it does not have to be re-entered.
        $name = isset($adhocNames[$email]) ? trim((string) $adhocNames[$email]) : '';
        $name = $name !== '' ? $name : null;
        $restaurant = isset($adhocRestaurants[$email]) ? trim((string) $adhocRestaurants[$email]) : '';
        $restaurant = $restaurant !== '' ? $restaurant : null;

        MercurialRecipient::query()->firstOrCreate(['email' => $key], ['name' => $name, 'restaurant' => $restaurant]);

        return [$name, $restaurant];
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
