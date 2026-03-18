<?php

namespace App\Models;

use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producer extends Model
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'region',
        'description',
        'image_url',
    ];

    /**
     * Get all products offered by this producer.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
