<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasAuditColumns, HasFactory;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'payment_date' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
