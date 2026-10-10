<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AreaAuthService;
use App\Services\AreaHomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private AreaAuthService $auth,
        private AreaHomeService $home,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user?->hasRole(...Role::ADMIN_PANEL_ROLES)) {
            return redirect()->route($this->home->routeFor($user));
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->auth->attempt($request, Role::ADMIN_PANEL_ROLES);

        return redirect()->route($this->home->routeFor(Auth::user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
