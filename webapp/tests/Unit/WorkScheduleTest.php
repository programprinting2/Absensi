<?php

namespace Tests\Unit;

use App\Models\WorkSchedule;
use Tests\TestCase;

class WorkScheduleTest extends TestCase
{
    public function test_implementation_summaries_use_descriptive_indonesian_labels(): void
    {
        $this->assertSame('Harian', WorkSchedule::previewFromRules('default')->implementationSummary());
        $this->assertSame(
            'Setiap Jumat',
            WorkSchedule::previewFromRules('weekdays', [5])->implementationSummary(),
        );
        $this->assertSame(
            'Setiap Tanggal 15',
            WorkSchedule::previewFromRules('month_days', null, [15])->implementationSummary(),
        );
        $this->assertSame(
            'Jumat, 21 Agt 2026',
            WorkSchedule::previewFromRules('specific_dates', null, null, ['2026-08-21'])->implementationSummary(),
        );
    }

    public function test_multiple_specific_dates_use_a_count_and_expose_tooltip_labels(): void
    {
        $schedule = WorkSchedule::previewFromRules(
            'specific_dates',
            null,
            null,
            ['2026-08-24', '2026-08-21'],
        );

        $this->assertSame('2 hari', $schedule->implementationSummary());
        $this->assertSame([
            'Jumat, 21 Agt 2026',
            'Senin, 24 Agt 2026',
        ], $schedule->implementationSpecificDateLabels());
    }
}
