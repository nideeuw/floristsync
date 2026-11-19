<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Midtrans order id used to pay an order. A member who switches payment method gets a new attempt. */
class PaymentAttempt extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
