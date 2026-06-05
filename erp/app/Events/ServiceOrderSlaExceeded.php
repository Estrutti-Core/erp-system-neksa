<?php

namespace App\Events;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceOrderSlaExceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServiceOrder $serviceOrder,
        public ServiceOrderStatus $status,
        public int $minutesExceeded
    ) {}
}
