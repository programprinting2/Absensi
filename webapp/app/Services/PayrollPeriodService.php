<?php

namespace App\Services;

use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

class PayrollPeriodService
{
    public function createPeriod(int $month, int $year): PayrollPeriod
    {
        $settings = PayrollSetting::active();

        $startDay = $settings->cutoff_start_day;
        $endDay = $settings->cutoff_end_day;

        if ($startDay <= $endDay) {
            $start = Carbon::create($year, $month, min($startDay, Carbon::create($year, $month)->daysInMonth));
            $end = Carbon::create($year, $month, min($endDay, Carbon::create($year, $month)->daysInMonth));
        } else {
            $prevMonth = Carbon::create($year, $month, 1)->subMonth();
            $start = Carbon::create($prevMonth->year, $prevMonth->month, min($startDay, $prevMonth->daysInMonth));
            $end = Carbon::create($year, $month, min($endDay, Carbon::create($year, $month)->daysInMonth));
        }

        $label = Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y');

        return PayrollPeriod::create([
            'period_start' => $start,
            'period_end' => $end,
            'label' => $label,
            'status' => 'draft',
        ]);
    }

    public function finalize(PayrollPeriod $period, User $user): void
    {
        if (! $period->isReview()) {
            throw new \RuntimeException('Hanya periode berstatus Review yang bisa difinalisasi.');
        }

        $period->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);

        app(CashBonService::class)->finalizeForPeriod($period);
    }

    public function unfinalize(PayrollPeriod $period): void
    {
        if (! $period->isFinalized()) {
            throw new \RuntimeException('Hanya periode berstatus Final yang bisa dibuka kembali.');
        }

        app(CashBonService::class)->unfinalizeForPeriod($period);

        $period->update([
            'status' => 'review',
            'finalized_at' => null,
            'finalized_by' => null,
        ]);
    }
}
