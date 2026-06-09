<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin();
    }
}
