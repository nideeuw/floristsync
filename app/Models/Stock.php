<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'date' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
