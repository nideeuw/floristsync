<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationVerificationService;
use App\Support\PasswordMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, RegistrationVerificationService $verification): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.unique' => 'This email is already registered. Try logging in or use another email.',
            ...PasswordMessages::forMember(),
        ]);

        try {
            $verification->start($request, $request->name, $request->email, $request->password);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'We could not send the verification code right now. Please try again in a moment.']);
        }

        return redirect()->route('register.verify');
    }
}
