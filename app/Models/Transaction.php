<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Services\ShippingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use HasAuditColumns, HasFactory;

    public const TYPE_POS = 'pos';

    public const TYPE_ONLINE = 'online';

    public const PENDING_PAYMENT = 'pending_payment';

    public const PROCESSING = 'processing';

    public const READY_FOR_PICKUP = 'ready_for_pickup';

    public const SHIPPED = 'shipped';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const CANCEL_PENDING = 'pending';

    public const CANCEL_APPROVED = 'approved';

    public const CANCEL_REJECTED = 'rejected';

    public const PAYMENT_WINDOW_MINUTES = 60;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'cancelled_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The number is built from the id, so it is set right after the insert.
        static::created(function (self $transaction) {
            if (! empty($transaction->getAttributes()['order_number'] ?? null)) {
                return;
            }

            $padded = str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);

            $transaction->forceFill([
                'order_number' => $transaction->transaction_type === self::TYPE_POS
                    ? 'POS-'.$padded
                    : 'FS-'.($transaction->created_on ?? now())->format('Ymd').'-'.$padded,
            ])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function isDelivery(): bool
    {
        return ! empty($this->courier);
    }

    public function paymentDeadline(): ?Carbon
    {
        return $this->created_on?->copy()->addMinutes(self::PAYMENT_WINDOW_MINUTES);
    }

    public function canResumePayment(): bool
    {
        $deadline = $this->paymentDeadline();

        return $this->transaction_type === self::TYPE_ONLINE
            && $this->status === self::PENDING_PAYMENT
            && $deadline !== null
            && $deadline->isFuture();
    }

    public function getFulfillmentAttribute(): string
    {
        if ($this->transaction_type === self::TYPE_POS) {
            return 'onsite';
        }

        return $this->isDelivery() ? 'delivery' : 'pickup';
    }

    public function getShippingServiceLabelAttribute(): ?string
    {
        if (! $this->isDelivery() || empty($this->courier)) {
            return null;
        }

        return ShippingService::formatServiceLabel(
            $this->courier,
            $this->shipping_service
        );
    }

    public function getOrderNumberAttribute(?string $value = null): string
    {
        return $value
            ?: ($this->payment?->midtrans_order_id
                ?? 'POS-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::PENDING_PAYMENT, self::CANCELLED]);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (is_numeric($value)) {
            return $this->where('id', $value)->first();
        }

        return $this->whereOrderNumber((string) $value)->first();
    }

    /**
     * Finds an order by its number. Old links keep working: the Midtrans id an order had before
     * order numbers existed (or in an older payment attempt) is accepted too.
     */
    public function scopeWhereOrderNumber(Builder $query, string $value): Builder
    {
        return $query->where(function (Builder $q) use ($value) {
            $q->where('order_number', $value)
                ->orWhereHas('payments', fn ($p) => $p->where('midtrans_order_id', $value))
                ->orWhereHas('payments.attempts', fn ($a) => $a->where('midtrans_order_id', $value));
        });
    }
}
