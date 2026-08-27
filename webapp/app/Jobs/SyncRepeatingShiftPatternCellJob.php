<?php

namespace App\Jobs;

use App\Services\ShiftCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncRepeatingShiftPatternCellJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public string $templateId,
        public string $workDate,
        public int $monthsAhead,
    ) {
        $this->onConnection('deferred');
    }

    public function uniqueId(): string
    {
        return $this->templateId.':'.$this->workDate;
    }

    public function handle(ShiftCalendarService $calendar): void
    {
        $calendar->materializeRepeatingPatternCell(
            $this->templateId,
            $this->workDate,
            $this->monthsAhead,
        );
    }
}
