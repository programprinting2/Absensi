<?php

namespace App\Http\Controllers;

use App\Models\CashBon;
use App\Models\Employee;
use App\Services\CashBonService;
use Illuminate\Http\Request;

class CashBonController extends Controller
{
    public function __construct(private CashBonService $cashBons) {}

    public function payload(Employee $employee)
    {
        return response()->json($this->cashBons->payloadFor($employee));
    }

    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:60'],
            'disbursed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cashBon = $this->cashBons->create(
            $employee,
            (float) $data['amount'],
            (int) $data['installment_count'],
            $data['disbursed_at'],
            $data['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash bon berhasil dicatat.',
            'item' => $this->cashBons->serializeCashBon($cashBon->load('installments')),
            'payload' => $this->cashBons->payloadFor($employee->fresh()),
        ], 201);
    }

    public function destroy(Employee $employee, CashBon $cashBon)
    {
        abort_unless($cashBon->employee_id === $employee->id, 404);

        try {
            $this->cashBons->cancel($cashBon);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cash bon dibatalkan.',
            'payload' => $this->cashBons->payloadFor($employee->fresh()),
        ]);
    }
}
