<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffService
{
    public const ROLES = [Role::CASHIER, Role::ADMIN_ORDER, Role::ADMIN_INVENTORY];

    public function roles(): Collection
    {
        return Role::query()
            ->whereIn('role_name', self::ROLES)
            ->where('is_active', true)
            ->orderBy('role_name')
            ->get();
    }

    public function manages(User $user): bool
    {
        return $user->hasRole(...self::ROLES);
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('role')
            ->whereHas('role', fn (Builder $q) => $q->whereIn('role_name', self::ROLES))
            ->when($filters['role_id'] ?? null, fn (Builder $q, $value) => $q->where('role_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $q, $value) => $q->where('is_active', $value === 'active'))
            ->when($filters['q'] ?? null, function (Builder $q, $value) {
                $term = '%' . trim($value) . '%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): User
    {
        $this->assertStaffRole((int) $data['role_id']);

        $staff = new User();
        $staff->fill(Arr::only($data, ['role_id', 'name', 'email', 'phone', 'password']));
        $staff->is_active = true;
        $staff->save();

        return $staff;
    }

    public function update(User $staff, array $data): User
    {
        if (! $this->manages($staff)) {
            throw new DomainException('Only staff accounts can be managed here.');
        }

        $this->assertStaffRole((int) $data['role_id']);

        $staff->fill(Arr::only($data, ['role_id', 'name', 'email', 'phone']));

        if (! empty($data['password'])) {
            $staff->password = $data['password'];
        }

        if (array_key_exists('is_active', $data)) {
            $staff->is_active = (bool) $data['is_active'];
        }

        $staff->save();

        if (! $staff->is_active) {
            $this->endSessions($staff);
        }

        return $staff;
    }

    public function deactivate(User $staff): void
    {
        if (! $this->manages($staff)) {
            throw new DomainException('Only staff accounts can be managed here.');
        }

        $staff->is_active = false;
        $staff->save();

        $this->endSessions($staff);
    }

    private function assertStaffRole(int $roleId): void
    {
        $allowed = Role::whereIn('role_name', self::ROLES)->whereKey($roleId)->exists();

        if (! $allowed) {
            throw new DomainException('Invalid staff role.');
        }
    }

    private function endSessions(User $staff): void
    {
        $staff->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $staff->id)->delete();
        }
    }
}
