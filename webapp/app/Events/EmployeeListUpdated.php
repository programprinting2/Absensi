<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class EmployeeListUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function broadcastOn(): array
    {
        return [new Channel('employees')];
    }

    public function broadcastAs(): string
    {
        return 'EmployeeListUpdated';
    }
}
