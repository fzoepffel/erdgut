<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * B2B contact form inside the restaurant shell. Sender identity comes from
 * the logged-in account (no name/email fields to fill or fake); the message
 * lands in the same ContactMessage inbox as the public forms, tagged with
 * the business name so the team sees the B2B context at a glance.
 */
class RestaurantContactController extends Controller
{
    public function create(Request $request): View
    {
        return view('restaurant.contact', [
            'profile' => $request->user()->restaurantProfile,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $business = $user->restaurantProfile?->business_name;

        $contactMessage = ContactMessage::query()->create([
            'name' => trim(($business ? $business.' · ' : '').$user->name),
            'email' => $user->email,
            'subject' => '[B2B] '.(filled($validated['subject'] ?? null) ? $validated['subject'] : __('restaurant.contact_title')),
            'message' => $validated['message'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $recipient = config('mail.contact_to') ?: config('mail.from.address');
        if (is_string($recipient) && $recipient !== '') {
            try {
                Mail::to($recipient)->send(new ContactMessageReceived($contactMessage));
            } catch (\Throwable $e) {
                report($e); // the message is stored either way
            }
        }

        return redirect()
            ->route('app.restaurant.contact')
            ->with('status', 'restaurant-contact-submitted');
    }
}
