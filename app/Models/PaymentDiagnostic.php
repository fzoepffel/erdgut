<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDiagnostic extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'phase',
        'checkout_id',
        'intent',
        'spargel_order_id',
        'payment_id',
        'ms_since_mount',
        'ua_summary',
        'meta',
        'ip_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'ms_since_mount' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
