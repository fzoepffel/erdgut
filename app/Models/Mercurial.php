<?php

namespace App\Models;

use App\Http\Controllers\Admin\AdminMercurialController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single weekly mercurial broadcast that was sent to restaurant customers.
 * {@see AdminMercurialController}.
 */
class Mercurial extends Model
{
    protected $fillable = [
        'subject',
        'subject_en',
        'body_html',
        'body_html_en',
        'recipients',
        'recipient_count',
        'created_by_user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'recipient_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
