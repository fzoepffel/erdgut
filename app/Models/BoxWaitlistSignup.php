<?php

namespace App\Models;

use Database\Factories\BoxWaitlistSignupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single waitlist entry for the upcoming subscription boxes.
 *
 * Captured in two places, both keyed by a unique email so the same person
 * is only ever asked once:
 *   - the homepage capture form (source 'homepage', always interested)
 *   - the post-payment checkout prompt (source 'checkout', interested = the
 *     yes/no answer; a "no" is still stored so we never re-ask).
 */
class BoxWaitlistSignup extends Model
{
    /** @use HasFactory<BoxWaitlistSignupFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'interested',
        'source',
        'locale',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'interested' => 'boolean',
        ];
    }
}
