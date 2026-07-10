<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreContactMessageRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Show the public contact form.
     */
    public function create(): View
    {
        return view('marketing.contact');
    }

    /**
     * Persist a valid contact message and notify team inbox.
     */
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Hidden honeypot field should stay empty for human submissions.
        if (filled($validated['website'] ?? null)) {
            return redirect()
                ->route('public.contact')
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
            try {
                Mail::to($recipient)->queue(new ContactMessageReceived($contactMessage));
            } catch (\Throwable $e) {
                report($e); // the message is stored either way
            }
        }

        return redirect()
            ->route('public.contact')
            ->with('status', 'contact-submitted');
    }
}
