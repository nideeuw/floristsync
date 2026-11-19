<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $category_id
 * @property string $product_name
 * @property string|null $description
 * @property float|int|string $price
 * @property string|null $image
 * @property string $status
 * @property bool $is_active
 * @property-read int $stock Computed from view_stock (see getStockAttribute and scopeWithStock)
 */
class Product extends Model
{
    use HasAuditColumns, HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_UNAVAILABLE = 'unavailable';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::created(function (Product $product) {
            $initialStock = isset($product->attributes['stock']) ? (int) $product->attributes['stock'] : 0;
            if ($initialStock > 0 && $product->stocks()->doesntExist()) {
                $product->stocks()->create([
                    'quantity_change' => $initialStock,
                    'change_type' => 'in',
                    'notes' => 'Initial stock',
                    'date' => now(),
                ]);
            }
        });

        static::updating(function (Product $product) {
            if ($product->isDirty('stock')) {
                $currentInView = (int) DB::table('view_stock')->where('product_id', $product->getKey())->value('stock');
                $diff = (int) $product->stock - $currentInView;
                if ($diff !== 0) {
                    $product->stocks()->create([
                        'quantity_change' => $diff,
                        'change_type' => 'adjustment',
                        'notes' => 'Stock updated via model',
                        'date' => now(),
                    ]);
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /** Current stock: uses the value loaded by withStock(), otherwise reads view_stock. */
    public function getStockAttribute($value): int
    {
        return (int) ($value ?? DB::table('view_stock')->where('product_id', $this->getKey())->value('stock'));
    }

    /** Active and available for sale (used by catalog, POS, and checkout). */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('products.is_active', true)
            ->where('products.status', self::STATUS_AVAILABLE);
    }

    /** Adds the `stock` attribute from view_stock (use view_stock.stock to filter or sort). */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->leftJoin('view_stock', 'view_stock.product_id', '=', 'products.id')
            ->addSelect('products.*', DB::raw('COALESCE(view_stock.stock, 0) as stock'));
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereIn('products.id', fn ($q) => $q->select('product_id')
            ->from('view_stock')
            ->where('stock', '<=', config('floristsync.low_stock_threshold')));
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->whereIn('products.id', fn ($q) => $q->select('product_id')
            ->from('view_stock')
            ->where('stock', '<=', 0));
    }
}
