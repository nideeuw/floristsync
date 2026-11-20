<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AccountReactivationService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, CartService $cart, AccountReactivationService $reactivation): RedirectResponse
    {
        $guestCart = session('cart', []);

        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            // Correct password of a self-deactivated member goes to the confirmation step.
            // Any other failure keeps the normal generic error.
            $member = $reactivation->findSelfDeactivated($request);

            if (! $member) {
                throw $e;
            }

            $reactivation->stash($request, $member);

            return redirect()->route('account.reactivate');
        }

        $request->session()->regenerate();

        if ($user = $request->user()) {
            $cart->mergeGuestCart($guestCart, $user);
        }
        $request->session()->forget('cart');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session. Back to the customer login door.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
