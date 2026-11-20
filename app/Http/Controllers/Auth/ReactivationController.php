<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountReactivationService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReactivationController extends Controller
{
    public function show(Request $request, AccountReactivationService $reactivation): View|RedirectResponse
    {
        $user = $reactivation->pending($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.reactivate', ['member' => $user]);
    }

    public function store(Request $request, AccountReactivationService $reactivation, CartService $cart): RedirectResponse
    {
        $user = $reactivation->pending($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $guestCart = session('cart', []);

        $reactivation->reactivate($user);
        $reactivation->forget($request);

        Auth::login($user);
        $request->session()->regenerate();

        $cart->mergeGuestCart($guestCart, $user);
        $request->session()->forget('cart');

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
