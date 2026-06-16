<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreContactMessageRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Shop-focused contact form. Mirrors {@see ContactController} but renders
 * inside the shop-marketing chrome (shop header, no account CTAs) and
 * keeps customers on the shop surface. Shares the same ContactMessage
 * storage, validation and team-inbox notification.
 */
class ShopContactController extends Controller
{
    /**
     * Show the shop contact form.
     */
    public function create(): View
    {
        return view('marketing.shop-contact');
    }

    /**
     * Persist a valid contact message and notify the team inbox.
     */
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Hidden honeypot field should stay empty for human submissions.
        if (filled($validated['website'] ?? null)) {
            return redirect()
                ->route('public.shop-contact')
                ->with('status', 'contact-submitted');
        }

        $contactMessage = ContactMessage::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $recipient = config('mail.contact_to') ?: config('mail.from.address');
        if (is_string($recipient) && $recipient !== '') {
            Mail::to($recipient)->send(new ContactMessageReceived($contactMessage));
        }

        return redirect()
            ->route('public.shop-contact')
            ->with('status', 'contact-submitted');
    }
}
