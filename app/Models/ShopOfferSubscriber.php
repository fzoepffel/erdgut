<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A persistent waitlist entry: someone who asked to be notified when the full
 * shop offer returns. They stay on the list across weeks and are notified every
 * reopen until they follow their unsubscribe link, which deletes the row.
 */
class ShopOfferSubscriber extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'unsubscribe_token',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'last_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ShopOfferSubscriber $subscriber): void {
            if (empty($subscriber->unsubscribe_token)) {
                $subscriber->unsubscribe_token = Str::random(48);
            }
        });
    }

    public function unsubscribeUrl(): string
    {
        return route('public.shop.notify.unsubscribe', ['token' => $this->unsubscribe_token]);
    }
}
