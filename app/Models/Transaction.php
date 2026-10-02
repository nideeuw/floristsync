<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasAuditColumns;

    public const TYPE_POS    = 'pos';
    public const TYPE_ONLINE = 'online';

    public const PENDING_PAYMENT  = 'pending_payment';
    public const PROCESSING       = 'processing';
    public const READY_FOR_PICKUP = 'ready_for_pickup';
    public const SHIPPED          = 'shipped';
    public const COMPLETED        = 'completed';
    public const CANCELLED        = 'cancelled';

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Latest payment (a transaction normally has one). */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** Delivery orders have a courier. Pickup and POS orders do not. */
    public function isDelivery(): bool
    {
        return ! empty($this->courier);
    }

    /** onsite (POS), pickup, or delivery. */
    public function getFulfillmentAttribute(): string
    {
        if ($this->transaction_type === self::TYPE_POS) {
            return 'onsite';
        }

        return $this->isDelivery() ? 'delivery' : 'pickup';
    }

    /** Online orders use the Midtrans order id. POS orders get a POS-000123 style number. */
    public function getOrderNumberAttribute(): string
    {
        return $this->payment?->midtrans_order_id
            ?? 'POS-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Paid / valid transactions (used for revenue). */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::PENDING_PAYMENT, self::CANCELLED]);
    }
}
