<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AreaAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private AreaAuthService $auth) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasRole(Role::CASHIER)) {
            return redirect()->route('cashier.pos');
        }

        return view('cashier.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->auth->attempt($request, [Role::CASHIER]);

        return redirect()->route('cashier.pos');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cashier.login');
    }
}
