<?php

namespace App\Policies;

use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Todos os usuários autenticados podem listar OS
    }

    public function view(User $user, ServiceOrder $serviceOrder): bool
    {
        // Técnicos só veem as próprias OS
        if ($user->isTechnician()) {
            return $serviceOrder->technician_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function update(User $user, ServiceOrder $serviceOrder): bool
    {
        if ($serviceOrder->isCancelled() || $serviceOrder->isCompleted()) {
            return $user->isAdmin();
        }

        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function delete(User $user, ServiceOrder $serviceOrder): bool
    {
        return $user->isAdmin();
    }

    public function changeStatus(User $user, ServiceOrder $serviceOrder): bool
    {
        // Técnicos só podem atualizar status das próprias OS
        if ($user->isTechnician()) {
            return $serviceOrder->technician_id === $user->id;
        }

        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function assignTechnician(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function generatePdf(User $user, ServiceOrder $serviceOrder): bool
    {
        return $this->view($user, $serviceOrder);
    }
}
