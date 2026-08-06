<?php

namespace App\Http\Controllers;

use App\Models\DeductionType;
use Illuminate\Http\Request;

class DeductionTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = DeductionType::query()
            ->when($request->boolean('active_only', $request->wantsJson()), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($types->map(fn (DeductionType $t) => $this->toOption($t))->values());
        }

        return view('payroll.deduction-types.index', ['deductionTypes' => $types]);
    }

    public function create()
    {
        return view('payroll.deduction-types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'calculation_method' => ['nullable', 'in:fixed,percentage'],
            'default_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['calculation_method'] = $data['calculation_method'] ?? 'fixed';
        $data['default_value'] = $data['default_value'] ?? 0;
        $data['is_active'] = true;

        $type = DeductionType::create($data);

        if ($request->wantsJson()) {
            return response()->json($this->toOption($type), 201);
        }

        return redirect()->route('payroll.deduction-types.index')->with('status', 'Jenis potongan berhasil ditambahkan.');
    }

    public function edit(DeductionType $deductionType)
    {
        return view('payroll.deduction-types.edit', compact('deductionType'));
    }

    public function update(Request $request, DeductionType $deductionType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'calculation_method' => ['sometimes', 'in:fixed,percentage'],
            'default_value' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $deductionType->update($data);

        if ($request->wantsJson()) {
            return response()->json($this->toOption($deductionType->fresh()));
        }

        return redirect()->route('payroll.deduction-types.index')->with('status', 'Jenis potongan berhasil diperbarui.');
    }

    public function destroy(Request $request, DeductionType $deductionType)
    {
        $deductionType->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('payroll.deduction-types.index')->with('status', 'Jenis potongan berhasil dihapus.');
    }

    private function toOption(DeductionType $type): array
    {
        $suffix = $type->calculation_method === 'percentage' ? '%' : 'Rp';

        return [
            'id' => $type->id,
            'name' => $type->name,
            'label' => "{$type->name} ({$suffix})",
            'value' => $type->name,
            'calculation_method' => $type->calculation_method,
            'default_value' => $type->default_value,
            'is_active' => $type->is_active,
        ];
    }
}
