<?php

namespace App\Observers;

use App\Models\CatalogAuditLog;
use App\Models\Producer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CatalogAuditObserver
{
    public function created(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }

        CatalogAuditLog::query()->create([
            'user_id' => Auth::id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => 'created',
            'old_values' => null,
            'new_values' => $this->snapshot($model),
        ]);
    }

    public function updated(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }

        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($changes) as $key) {
            $oldValues[$key] = $model->getOriginal($key);
        }

        CatalogAuditLog::query()->create([
            'user_id' => Auth::id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $changes,
        ]);
    }

    public function deleted(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }

        CatalogAuditLog::query()->create([
            'user_id' => Auth::id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => 'deleted',
            'old_values' => $this->snapshot($model),
            'new_values' => null,
        ]);
    }

    private function shouldAudit(Model $model): bool
    {
        if (! $model instanceof Product && ! $model instanceof Producer) {
            return false;
        }

        $user = Auth::user();

        return $user !== null && $user->can('admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $model): array
    {
        return $model->getAttributes();
    }
}
