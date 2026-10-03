<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AreaAuthService
{
    /**
     * Log a user in only when the account's role belongs to this area.
     * A wrong-area account gets the same generic error as a wrong password.
     *
     * @param  string[]  $roles
     */
    public function attempt(Request $request, array $roles): void
    {
        $key = Str::transliterate(
            Str::lower((string) $request->input('email')) . '|' . $request->ip() . '|' . implode(',', $roles)
        );

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ]);
        }

        $user = User::where('email', $request->input('email'))
            ->where('is_active', true)
            ->first();

        if (
            ! $user
            || ! Hash::check((string) $request->input('password'), $user->password)
            || ! $user->hasRole(...$roles)
        ) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($key);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
    }
}
