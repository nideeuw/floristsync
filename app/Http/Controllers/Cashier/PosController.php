<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(ShiftService $shifts): View
    {
        $cashier = auth()->user();
        $shift = $cashier ? ($shifts->current($cashier) ?? $shifts->open($cashier)) : null;

        return view('cashier.pos', [
            'shift' => $shift,
        ]);
    }
}
