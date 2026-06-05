<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quote $quote): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function update(User $user, Quote $quote): bool
    {
        if ($quote->isConverted()) {
            return false; // Orçamentos convertidos são imutáveis
        }
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }
}
