<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, HasAuditColumns;

    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /** Active and available for sale (used by catalog, POS, and checkout). */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('products.is_active', true)
            ->where('products.status', self::STATUS_AVAILABLE);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('products.stock', '<=', config('floristsync.low_stock_threshold'));
    }
}
