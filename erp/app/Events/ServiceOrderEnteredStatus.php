<?php

namespace App\Events;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceOrderEnteredStatus
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServiceOrder $serviceOrder,
        public ServiceOrderStatus $status,
        public Carbon $enteredAt
    ) {}
}
