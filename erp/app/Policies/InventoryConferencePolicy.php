<?php

namespace App\Policies;

use App\Models\InventoryConference;
use App\Models\User;

class InventoryConferencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InventoryConference $conference): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true; // Qualquer técnico ou operador pode registrar recebimento
    }

    public function update(User $user, InventoryConference $conference): bool
    {
        return true;
    }

    public function delete(User $user, InventoryConference $conference): bool
    {
        return $user->isAdmin();
    }
}
