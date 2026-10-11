<?php

namespace App\Services;

use App\Models\User;
use LogicException;

class StaffService
{
    public function create(array $data): User
    {
        throw new LogicException('Not implemented yet.');
    }

    public function update(User $staff, array $data): User
    {
        throw new LogicException('Not implemented yet.');
    }

    public function deactivate(User $staff): void
    {
        throw new LogicException('Not implemented yet.');
    }
}
