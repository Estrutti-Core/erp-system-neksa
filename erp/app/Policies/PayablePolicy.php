<?php

namespace App\Policies;

use App\Models\Payable;
use App\Models\User;

class PayablePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payable $payable): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function update(User $user, Payable $payable): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function delete(User $user, Payable $payable): bool
    {
        return false;
    }
}
