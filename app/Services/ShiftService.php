<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use LogicException;

/**
 * Stub: a cashier shift opens at login and closes at logout.
 *
 * Hook points for the real implementation:
 * - open():  call it in Cashier\LoginController::store() right after AreaAuthService::attempt().
 * - close(): call it in Cashier\LoginController::destroy() before Auth::logout().
 * - Fill transactions.shift_id from current() in TransactionService::createPosTransaction().
 *
 * Do not call these stubs before they are implemented, or the cashier login will break.
 */
class ShiftService
{
    public function current(User $cashier): ?Shift
    {
        throw new LogicException('Not implemented yet.');
    }

    public function open(User $cashier): Shift
    {
        throw new LogicException('Not implemented yet.');
    }

    public function close(Shift $shift): Shift
    {
        throw new LogicException('Not implemented yet.');
    }
}
