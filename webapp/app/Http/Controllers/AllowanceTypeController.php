<?php

namespace App\Http\Controllers;

use App\Models\AllowanceType;
use Illuminate\Http\Request;

class AllowanceTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = AllowanceType::query()
            ->when($request->boolean('active_only', $request->wantsJson()), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($types->map(fn (AllowanceType $t) => $this->toOption($t))->values());
        }

        return view('payroll.allowance-types.index', ['allowanceTypes' => $types]);
    }

    public function create()
    {
        return view('payroll.allowance-types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_fixed' => ['sometimes', 'boolean'],
        ]);

        $data['is_fixed'] = $request->boolean('is_fixed', true);
        $data['is_active'] = true;

        $type = AllowanceType::create($data);

        if ($request->wantsJson()) {
            return response()->json($this->toOption($type), 201);
        }

        return redirect()->route('payroll.allowance-types.index')->with('status', 'Jenis tunjangan berhasil ditambahkan.');
    }

    public function edit(AllowanceType $allowanceType)
    {
        return view('payroll.allowance-types.edit', compact('allowanceType'));
    }

    public function update(Request $request, AllowanceType $allowanceType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_fixed' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_fixed')) {
            $data['is_fixed'] = $request->boolean('is_fixed');
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $allowanceType->update($data);

        if ($request->wantsJson()) {
            return response()->json($this->toOption($allowanceType->fresh()));
        }

        return redirect()->route('payroll.allowance-types.index')->with('status', 'Jenis tunjangan berhasil diperbarui.');
    }

    public function destroy(Request $request, AllowanceType $allowanceType)
    {
        $allowanceType->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('payroll.allowance-types.index')->with('status', 'Jenis tunjangan berhasil dihapus.');
    }

    private function toOption(AllowanceType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'label' => $type->name,
            'value' => $type->name,
            'is_fixed' => $type->is_fixed,
            'is_active' => $type->is_active,
        ];
    }
}
