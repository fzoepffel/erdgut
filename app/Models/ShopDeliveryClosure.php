<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ShopDeliveryClosure extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'starts_on',
        'ends_on',
        'reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function openDayExceptions(): HasMany
    {
        return $this->hasMany(ShopDeliveryClosureException::class);
    }

    public function isSingleDay(): bool
    {
        return $this->starts_on->toDateString() === $this->ends_on->toDateString();
    }

    public function covers(Carbon $date): bool
    {
        $iso = $date->copy()->startOfDay()->toDateString();

        return $this->starts_on->toDateString() <= $iso
            && $this->ends_on->toDateString() >= $iso;
    }

    /**
     * @param  Builder<ShopDeliveryClosure>  $query
     */
    public function scopeOverlapping($query, Carbon $startsOn, Carbon $endsOn, ?int $exceptId = null): void
    {
        $start = $startsOn->copy()->startOfDay()->toDateString();
        $end = $endsOn->copy()->startOfDay()->toDateString();

        $query
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId));
    }
}
