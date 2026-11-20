<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only record of who changed what. The app never edits or deletes these rows. */
class AuditLog extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Status changes of one transaction, oldest first. */
    public static function statusHistory(int $transactionId): Collection
    {
        return static::query()
            ->with('user:id,name')
            ->where('entity', 'transaction')
            ->where('entity_id', $transactionId)
            ->where('action', 'order.status_changed')
            ->orderBy('id')
            ->get();
    }

    public function actorLabel(): string
    {
        return ($this->user?->name ?? 'System').' ('.$this->source.')';
    }

    /** @return list<string> */
    public function changeLines(): array
    {
        $lines = [];

        foreach ($this->new_values ?? [] as $key => $value) {
            $lines[] = $key.': '.$this->show($this->old_values[$key] ?? null).' -> '.$this->show($value);
        }

        return $lines;
    }

    private function show(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => (string) json_encode($value),
            default => (string) $value,
        };
    }
}
