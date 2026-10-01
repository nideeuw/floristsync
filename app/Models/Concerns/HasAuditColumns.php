<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @method static void creating(\Closure|string $callback)
 * @method static void updating(\Closure|string $callback)
 */
trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            $model->created_by ??= Auth::id();
            $model->modified_by ??= Auth::id();
        });

        static::updating(function ($model) {
            if (! $model->isDirty('modified_by')) {
                $model->modified_by = Auth::id();
            }
        });
    }

    public function getCreatedAtColumn(): string
    {
        return 'created_on';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'modified_on';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->getTable() . '.is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
