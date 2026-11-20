<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AccountReactivationService
{
    private const SESSION_KEY = 'reactivate_member';

    /** Seconds the confirmation page stays valid after the password was verified. */
    private const TTL = 600;

    /**
     * Return the member only when the email + password are correct AND the account
     * was deactivated by the member themselves. Everything else returns null so the
     * caller can show the normal generic login error.
     */
    public function findSelfDeactivated(Request $request): ?User
    {
        $user = User::where('email', $request->input('email'))
            ->where('is_active', false)
            ->first();

        if (! $user || ! $this->eligible($user)) {
            return null;
        }

        $key = 'reactivate|' . Str::lower((string) $request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return null;
        }

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($key);

            return null;
        }

        RateLimiter::clear($key);

        return $user;
    }

    public function stash(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, ['id' => $user->id, 'at' => time()]);
    }

    /**
     * The member waiting for the confirmation step, or null (also when it expired or is no longer valid).
     */
    public function pending(Request $request): ?User
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data) || (time() - (int) ($data['at'] ?? 0)) > self::TTL) {
            $this->forget($request);

            return null;
        }

        $user = User::where('is_active', false)->find($data['id'] ?? 0);

        if (! $user || ! $this->eligible($user)) {
            $this->forget($request);

            return null;
        }

        return $user;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function reactivate(User $user): void
    {
        $user->forceFill([
            'is_active' => true,
            'deactivated_by' => null,
            'modified_by' => $user->id,
        ])->save();
    }

    private function eligible(User $user): bool
    {
        return ! $user->is_active
            && $user->deactivated_by !== null
            && (int) $user->deactivated_by === (int) $user->id
            && $user->hasRole(Role::MEMBER);
    }
}
