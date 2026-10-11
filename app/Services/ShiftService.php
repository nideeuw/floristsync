<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use LogicException;

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
