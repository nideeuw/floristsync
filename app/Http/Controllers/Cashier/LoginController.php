<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AreaAuthService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public function __construct(
        private AreaAuthService $auth,
        private ShiftService $shifts,
    ) {}

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

        try {
            /** @var User|null $cashier */
            $cashier = Auth::user() ?? $request->user();
            if ($cashier) {
                $this->shifts->open($cashier);
            }
        } catch (Throwable $e) {
            Log::error('Failed to open cashier shift: ' . $e->getMessage(), [
                'cashier_id' => Auth::id() ?? $request->user()?->id,
                'exception' => $e,
            ]);
        }

        return redirect()->route('cashier.pos');
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            /** @var User|null $cashier */
            $cashier = Auth::user() ?? $request->user();
            if ($cashier) {
                $shift = $this->shifts->current($cashier);
                if ($shift) {
                    $this->shifts->close($shift);
                }
            }
        } catch (Throwable $e) {
            Log::error('Failed to close cashier shift: ' . $e->getMessage(), [
                'cashier_id' => Auth::id() ?? $request->user()?->id,
                'exception' => $e,
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cashier.login');
    }
}
