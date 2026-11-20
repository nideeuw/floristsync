<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    // Keys that must never reach the log, at any depth.
    private const SENSITIVE = ['password', 'token', 'secret', 'signature', 'remember', 'snap'];

    public function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
        ?string $source = null,
    ): AuditLog {
        $actor = ($userId !== null && $userId !== Auth::id()) ? User::find($userId) : Auth::user();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'source' => $source ?? $this->source($actor instanceof User ? $actor : null),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_values' => $old === null ? null : $this->scrub($old),
            'new_values' => $new === null ? null : $this->scrub($new),
            'ip' => request()?->ip(),
        ]);
    }

    /** Logs only the fields that really changed. Returns null when nothing changed. */
    public function logChanges(string $action, string $entity, ?int $entityId, array $before, array $after): ?AuditLog
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $was = $before[$key] ?? null;

            if ($this->normalize($was) !== $this->normalize($value)) {
                $old[$key] = $was;
                $new[$key] = $value;
            }
        }

        return $new === [] ? null : $this->log($action, $entity, $entityId, $old, $new);
    }

    public function source(?User $actor = null): string
    {
        if ($this->insideConsoleCommand()) {
            return 'scheduler';
        }

        if (request()?->is('api/midtrans/*')) {
            return 'webhook';
        }

        $actor ??= Auth::user();

        if ($actor instanceof User) {
            return match (true) {
                $actor->hasRole(Role::MEMBER) => 'member',
                $actor->hasRole(Role::CASHIER) => 'pos',
                default => 'admin',
            };
        }

        return 'system';
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->when($filters['user_id'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when(filled($filters['action'] ?? null), fn ($q) => $q->where('action', $filters['action']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('created_on', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('created_on', '<=', $filters['to']))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function actors(): Collection
    {
        return User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function actions(): Collection
    {
        return AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
    }

    /**
     * True when the change is made by one of our artisan commands (for example orders:expire-pending).
     * The call stack is used because Artisan::call() does not fire the CommandStarting event.
     */
    private function insideConsoleCommand(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            if (isset($frame['class']) && str_starts_with($frame['class'], 'App\\Console\\Commands\\')) {
                return true;
            }
        }

        return false;
    }

    private function scrub(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && Str::contains(Str::lower($key), self::SENSITIVE)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }

    private function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_array($value) => (string) json_encode($value),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
