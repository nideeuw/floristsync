<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_THROTTLED) {
            $throttle = (int) config('auth.passwords.users.throttle', 60);
            $record = DB::table('password_reset_tokens')->where('email', $request->input('email'))->first();
            $remaining = $record && isset($record->created_at)
                ? max(1, $throttle - (int) now()->diffInSeconds(Carbon::parse($record->created_at)))
                : $throttle;

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Please wait {$remaining} seconds before retrying."]);
        }

        // Prevent email enumeration: return the same neutral success message
        // whether the user was found or not.
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
