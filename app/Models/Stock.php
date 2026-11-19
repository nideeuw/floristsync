<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasAuditColumns;

    public const REASON_WILTED = 'wilted';

    public const REASON_DAMAGED = 'damaged';

    public const REASON_GIFT_SAMPLE = 'gift_sample';

    public const REASON_MISCOUNT = 'miscount';

    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_WILTED => 'Wilted',
        self::REASON_DAMAGED => 'Damaged',
        self::REASON_GIFT_SAMPLE => 'Gift / Sample',
        self::REASON_MISCOUNT => 'Miscount',
        self::REASON_OTHER => 'Other',
    ];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'date' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
