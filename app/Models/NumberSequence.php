<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Gap-free, concurrency-safe counter keyed by an arbitrary string.
 *
 * @property string $key
 * @property int $value
 */
class NumberSequence extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /**
     * Atomically allocate the next value for $key.
     *
     * MUST be called inside an open DB transaction so the row lock is held
     * until the caller commits — that is what guarantees sequential, never-
     * duplicated numbers under concurrency (InnoDB row lock on MySQL, whole-DB
     * write lock on SQLite). A rolled-back caller therefore consumes no number.
     */
    public static function next(string $key): int
    {
        $row = static::query()->lockForUpdate()->find($key);

        if ($row === null) {
            // First use of this key. Insert at 0, then re-read under the lock.
            // On a concurrent first-insert race the loser's insert fails on the
            // primary key; it then reads the now-existing, locked row.
            try {
                static::query()->insert([
                    'key' => $key,
                    'value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                // lost the race — fall through to the locked read
            }
            $row = static::query()->lockForUpdate()->find($key);
        }

        $next = (int) $row->value + 1;
        $row->forceFill(['value' => $next])->save();

        return $next;
    }
}
