<?php

namespace App\Services;

use App\Models\CashBon;
use App\Models\CashBonInstallment;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashBonService
{
    /**
     * Buat cash bon + jadwal cicilan.
     * Sisa pembagian rupiah dimasukkan ke cicilan terakhir.
     */
    public function create(
        Employee $employee,
        float $amount,
        int $installmentCount,
        string $disbursedAt,
        ?string $notes = null,
    ): CashBon {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal cash bon harus lebih dari 0.');
        }

        if ($installmentCount < 1 || $installmentCount > 60) {
            throw new \InvalidArgumentException('Jumlah cicilan harus antara 1–60.');
        }

        $amountCents = (int) round($amount * 100);
        $baseCents = intdiv($amountCents, $installmentCount);
        $amounts = [];

        for ($i = 1; $i <= $installmentCount; $i++) {
            $cents = $i === $installmentCount
                ? $amountCents - ($baseCents * ($installmentCount - 1))
                : $baseCents;
            $amounts[] = $cents / 100;
        }

        return DB::transaction(function () use ($employee, $amount, $installmentCount, $amounts, $disbursedAt, $notes) {
            $cashBon = CashBon::create([
                'employee_id' => $employee->id,
                'amount' => $amount,
                'installment_count' => $installmentCount,
                'installment_amount' => $amounts[0],
                'remaining_amount' => $amount,
                'disbursed_at' => $disbursedAt,
                'notes' => $notes,
                'status' => 'active',
                'created_at' => now(),
            ]);

            foreach ($amounts as $index => $installmentAmount) {
                CashBonInstallment::create([
                    'cash_bon_id' => $cashBon->id,
                    'sequence' => $index + 1,
                    'amount' => $installmentAmount,
                    'status' => 'pending',
                ]);
            }

            return $cashBon->load('installments');
        });
    }

    /**
     * Cicilan berikutnya (1 per cash bon aktif) yang siap dipotong gaji.
     *
     * @return Collection<int, CashBonInstallment>
     */
    public function nextPendingInstallments(Employee $employee): Collection
    {
        $bons = CashBon::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->with(['installments' => fn ($q) => $q->where('status', 'pending')->orderBy('sequence')])
            ->orderBy('disbursed_at')
            ->get();

        return $bons
            ->map(fn (CashBon $bon) => $bon->installments->first())
            ->filter()
            ->values();
    }

    public function pendingTotalFor(Employee $employee): float
    {
        return (float) $this->nextPendingInstallments($employee)->sum('amount');
    }

    /**
     * Ubah nominal cicilan yang sedang dipotong di slip (status deducted).
     * Jika lebih kecil: selisih jadi cicilan pending baru.
     * Jika lebih besar: ambil dari cicilan pending berikutnya.
     */
    public function adjustDeductedAmount(CashBonInstallment $installment, float $newAmount): void
    {
        if (! in_array($installment->status, ['deducted', 'paid'], true)) {
            throw new \InvalidArgumentException('Hanya cicilan yang terikat ke slip yang bisa diubah.');
        }

        if ($installment->status === 'paid') {
            throw new \InvalidArgumentException('Cicilan yang sudah dibayar tidak bisa diubah.');
        }

        $newAmount = round($newAmount, 2);
        if ($newAmount <= 0) {
            throw new \InvalidArgumentException('Nominal potongan harus lebih dari 0.');
        }

        $bon = $installment->cashBon()->with('installments')->firstOrFail();
        $oldAmount = round((float) $installment->amount, 2);
        $diff = round($oldAmount - $newAmount, 2);

        if (abs($diff) < 0.01) {
            return;
        }

        DB::transaction(function () use ($installment, $bon, $newAmount, $diff, $oldAmount) {
            if ($diff > 0) {
                // Potongan lebih kecil → sisa jadi cicilan pending berikutnya.
                $installment->update(['amount' => $newAmount]);

                $maxSeq = (int) $bon->installments()->max('sequence');
                CashBonInstallment::create([
                    'cash_bon_id' => $bon->id,
                    'sequence' => $maxSeq + 1,
                    'amount' => $diff,
                    'status' => 'pending',
                ]);
            } else {
                // Potongan lebih besar → ambil dari cicilan pending.
                $need = abs($diff);
                $available = (float) $bon->installments()->where('status', 'pending')->sum('amount');

                if ($need > $available + 0.009) {
                    throw new \InvalidArgumentException(
                        'Nominal melebihi sisa cash bon (maks. Rp '.number_format($oldAmount + $available, 0, ',', '.').').'
                    );
                }

                $installment->update(['amount' => $newAmount]);

                $pendings = $bon->installments()
                    ->where('status', 'pending')
                    ->orderBy('sequence')
                    ->get();

                foreach ($pendings as $pending) {
                    if ($need <= 0.009) {
                        break;
                    }

                    $pendingAmount = (float) $pending->amount;
                    if ($pendingAmount <= $need + 0.009) {
                        $need = round($need - $pendingAmount, 2);
                        $pending->delete();
                    } else {
                        $pending->update(['amount' => round($pendingAmount - $need, 2)]);
                        $need = 0;
                    }
                }
            }

            $bon->refresh();
            $bon->update([
                'installment_count' => $bon->installments()->count(),
            ]);
            $bon->refreshRemaining();
        });
    }

    /**
     * Lepas cicilan yang terikat ke entry (saat regenerate/recalculate).
     */
    public function releaseFromEntry(PayrollEntry $entry): void
    {
        $installments = CashBonInstallment::query()
            ->where('payroll_entry_id', $entry->id)
            ->whereIn('status', ['deducted', 'paid'])
            ->with('cashBon')
            ->get();

        foreach ($installments as $installment) {
            // Jangan buka lagi jika periode sudah finalized & status paid — tetap izinkan
            // release saat recalculate sebelum finalize (status deducted).
            if ($installment->status === 'paid') {
                $periodStatus = $entry->period?->status;
                if ($periodStatus === 'finalized') {
                    continue;
                }
            }

            $installment->update([
                'status' => 'pending',
                'payroll_entry_id' => null,
                'paid_at' => null,
            ]);

            if ($installment->cashBon && $installment->cashBon->status === 'paid') {
                $installment->cashBon->update(['status' => 'active']);
            }

            $installment->cashBon?->refreshRemaining();
        }
    }

    /**
     * Tandai cicilan terpotong pada entry gaji + kembalikan total potongan.
     *
     * @return Collection<int, CashBonInstallment>
     */
    public function applyToEntry(PayrollEntry $entry, Employee $employee): Collection
    {
        $installments = $this->nextPendingInstallments($employee);

        foreach ($installments as $installment) {
            $installment->update([
                'status' => 'deducted',
                'payroll_entry_id' => $entry->id,
            ]);
            $installment->cashBon?->refreshRemaining();
        }

        return $installments;
    }

    /**
     * Saat periode digaji difinalisasi: deducted → paid.
     */
    public function finalizeForPeriod(PayrollPeriod $period): void
    {
        $entryIds = $period->entries()->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
        }

        $installments = CashBonInstallment::query()
            ->whereIn('payroll_entry_id', $entryIds)
            ->where('status', 'deducted')
            ->with('cashBon')
            ->get();

        foreach ($installments as $installment) {
            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            $installment->cashBon?->refreshRemaining();
        }
    }

    /**
     * Buka finalisasi periode: paid (dari periode ini) → deducted kembali.
     */
    public function unfinalizeForPeriod(PayrollPeriod $period): void
    {
        $entryIds = $period->entries()->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
        }

        $installments = CashBonInstallment::query()
            ->whereIn('payroll_entry_id', $entryIds)
            ->where('status', 'paid')
            ->with('cashBon')
            ->get();

        foreach ($installments as $installment) {
            $installment->update([
                'status' => 'deducted',
                'paid_at' => null,
            ]);

            if ($installment->cashBon && $installment->cashBon->status === 'paid') {
                $installment->cashBon->update(['status' => 'active']);
            }

            $installment->cashBon?->refreshRemaining();
        }
    }

    public function cancel(CashBon $cashBon): void
    {
        if ($cashBon->installments()->where('status', 'paid')->exists()) {
            throw new \RuntimeException('Cash bon yang sudah ada cicilan terbayar tidak bisa dibatalkan penuh. Batalkan sisa cicilan pending saja.');
        }

        DB::transaction(function () use ($cashBon) {
            $cashBon->installments()
                ->whereIn('status', ['pending', 'deducted'])
                ->update([
                    'status' => 'cancelled',
                    'payroll_entry_id' => null,
                    'paid_at' => null,
                ]);

            $cashBon->update([
                'status' => 'cancelled',
                'remaining_amount' => 0,
            ]);
        });
    }

    /**
     * Daftar semua cash bon (untuk halaman menu).
     *
     * @return array{items: array<int, array<string, mixed>>, active_remaining: float, active_count: int}
     */
    public function indexPayload(?string $status = null): array
    {
        $query = CashBon::query()
            ->with(['employee:id,full_name,employee_code', 'installments.payrollEntry.period'])
            ->orderByDesc('disbursed_at')
            ->orderByDesc('created_at');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $bons = $query->get();

        return [
            'items' => $bons->map(fn (CashBon $bon) => array_merge(
                $this->serializeCashBon($bon),
                [
                    'employee' => [
                        'id' => $bon->employee?->id,
                        'full_name' => $bon->employee?->full_name,
                        'employee_code' => $bon->employee?->employee_code,
                    ],
                ],
            ))->values()->all(),
            'active_remaining' => (float) CashBon::query()->where('status', 'active')->sum('remaining_amount'),
            'active_count' => (int) CashBon::query()->where('status', 'active')->count(),
        ];
    }

    /**
     * Payload ringkas untuk UI per karyawan.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(Employee $employee): array
    {
        $bons = CashBon::query()
            ->where('employee_id', $employee->id)
            ->with(['installments.payrollEntry.period'])
            ->orderByDesc('disbursed_at')
            ->orderByDesc('created_at')
            ->get();

        return [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ],
            'items' => $bons->map(fn (CashBon $bon) => $this->serializeCashBon($bon))->values(),
            'active_remaining' => (float) $bons->where('status', 'active')->sum('remaining_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCashBon(CashBon $bon): array
    {
        return [
            'id' => $bon->id,
            'amount' => (float) $bon->amount,
            'amount_label' => 'Rp '.number_format((float) $bon->amount, 0, ',', '.'),
            'installment_count' => $bon->installment_count,
            'installment_amount' => (float) $bon->installment_amount,
            'installment_amount_label' => 'Rp '.number_format((float) $bon->installment_amount, 0, ',', '.'),
            'remaining_amount' => (float) $bon->remaining_amount,
            'remaining_amount_label' => 'Rp '.number_format((float) $bon->remaining_amount, 0, ',', '.'),
            'disbursed_at' => $bon->disbursed_at->format('Y-m-d'),
            'disbursed_at_label' => $bon->disbursed_at->locale('id')->translatedFormat('j F Y'),
            'notes' => $bon->notes,
            'status' => $bon->status,
            'status_label' => match ($bon->status) {
                'active' => 'Berjalan',
                'paid' => 'Lunas',
                'cancelled' => 'Dibatalkan',
                default => $bon->status,
            },
            'installments' => $bon->installments->map(function (CashBonInstallment $inst) use ($bon) {
                $periodLabel = $inst->payrollEntry?->period?->label;

                return [
                    'id' => $inst->id,
                    'sequence' => $inst->sequence,
                    'label' => "Cicilan {$inst->sequence}/{$bon->installment_count}",
                    'amount' => (float) $inst->amount,
                    'amount_label' => 'Rp '.number_format((float) $inst->amount, 0, ',', '.'),
                    'status' => $inst->status,
                    'status_label' => match ($inst->status) {
                        'pending' => 'Menunggu',
                        'deducted' => 'Dipotong (belum final)',
                        'paid' => 'Lunas',
                        'cancelled' => 'Dibatalkan',
                        default => $inst->status,
                    },
                    'period_label' => $periodLabel,
                    'paid_at' => $inst->paid_at?->format('Y-m-d H:i'),
                    'paid_at_label' => $inst->paid_at?->locale('id')->translatedFormat('j M Y H:i'),
                ];
            })->values(),
        ];
    }
}
