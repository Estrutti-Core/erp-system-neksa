<?php

namespace App\Events;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServiceOrder $serviceOrder,
        public ?ServiceOrderStatus $fromStatus,
        public ServiceOrderStatus $toStatus,
        public ?User $user = null,
        public ?string $notes = null
    ) {}
}
