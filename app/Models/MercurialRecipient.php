<?php

namespace App\Models;

use Database\Factories\MercurialRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hand-added mercurial contact that is not a restaurant User account.
 * Stored so the admin never has to re-type the same address each week.
 */
class MercurialRecipient extends Model
{
    /** @use HasFactory<MercurialRecipientFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
    ];
}
