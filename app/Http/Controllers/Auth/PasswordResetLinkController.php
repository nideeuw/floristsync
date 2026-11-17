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
use Throwable;

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

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send the email right now. Please try again in a few minutes.']);
        }

        if ($status === Password::RESET_THROTTLED) {
            $throttle = (int) config('auth.passwords.users.throttle', 60);
            $record = DB::table('password_reset_tokens')->where('email', $request->input('email'))->first();
            $remaining = $record && isset($record->created_at)
                ? max(1, $throttle - (int) now()->diffInSeconds(Carbon::parse($record->created_at)))
                : $throttle;

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Please wait {$remaining} seconds before retrying."]);
        }

        return back()->with('status', 'If that email is registered, we have sent a password reset link. Please check your inbox and your spam folder.');
    }
}
