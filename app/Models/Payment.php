<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasAuditColumns, HasFactory;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'payment_date' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * Call this before midtrans_order_id is replaced. The current Midtrans id stays as a closed attempt
     * and the new id becomes a pending one, so a late notification for the old id still finds this payment.
     */
    public function recordAttempt(string $newOrderId, ?string $newMethod): void
    {
        if ($this->midtrans_order_id) {
            $previous = $this->attempts()->firstOrCreate(
                ['midtrans_order_id' => $this->midtrans_order_id],
                ['status' => $this->payment_status, 'method' => $this->payment_method]
            );

            $previous->update([
                'status' => $this->payment_status,
                'method' => $this->payment_method ?? $previous->method,
            ]);
        }

        $this->attempts()->create([
            'midtrans_order_id' => $newOrderId,
            'method' => $newMethod,
            'status' => 'pending',
        ]);
    }
}
