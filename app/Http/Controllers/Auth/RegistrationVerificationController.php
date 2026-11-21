<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\RegistrationVerificationService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class RegistrationVerificationController extends Controller
{
    public function show(Request $request, RegistrationVerificationService $verification): View|RedirectResponse
    {
        $pending = $verification->pending($request);

        if (! $pending) {
            return redirect()->route('register');
        }

        return view('auth.verify-registration', [
            'maskedEmail' => $verification->maskEmail($pending['email']),
            'cooldown' => $verification->cooldownLeft($pending),
            'minutes' => intdiv(RegistrationVerificationService::CODE_TTL, 60),
        ]);
    }

    public function store(Request $request, RegistrationVerificationService $verification, CartService $cart): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $guestCart = session('cart', []);

        try {
            $user = $verification->verify($request, (string) $request->input('code'));
        } catch (DomainException $e) {
            // The registration is gone (expired, too many tries, email taken): back to the form.
            if (! $verification->pending($request)) {
                return redirect()->route('register')->withErrors(['email' => $e->getMessage()]);
            }

            return back()->withErrors(['code' => $e->getMessage()]);
        }

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $cart->mergeGuestCart($guestCart, $user);
        $request->session()->forget('cart');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request, RegistrationVerificationService $verification): RedirectResponse
    {
        try {
            $verification->resend($request);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['code' => 'We could not send the code right now. Please try again in a moment.']);
        }

        return back()->with('status', 'A new code has been sent to your email.');
    }

    /** "Use a different email": back to the form with the typed name and email filled in. */
    public function cancel(Request $request, RegistrationVerificationService $verification): RedirectResponse
    {
        $pending = $verification->pending($request);
        $verification->forget($request);

        return redirect()->route('register')->withInput([
            'name' => $pending['name'] ?? '',
            'email' => $pending['email'] ?? '',
        ]);
    }
}
