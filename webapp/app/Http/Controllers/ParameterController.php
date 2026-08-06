<?php

namespace App\Http\Controllers;

use App\Models\Parameter;
use App\Models\ParameterDetail;
use App\Services\ParameterService;
use Illuminate\Http\Request;

class ParameterController extends Controller
{
    public function index()
    {
        $parameters = Parameter::withCount('details')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($parameters);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:parameters,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? ((int) Parameter::max('sort_order') + 1);

        $parameter = Parameter::create($data);
        ParameterService::clearCache($parameter->name);

        return response()->json($parameter->loadCount('details'), 201);
    }

    public function update(Request $request, Parameter $parameter)
    {
        $oldName = $parameter->name;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:parameters,name,'.$parameter->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $parameter->update($data);
        ParameterService::clearCache($oldName);
        ParameterService::clearCache($parameter->name);

        return response()->json($parameter->fresh()->loadCount('details'));
    }

    public function destroy(Parameter $parameter)
    {
        $name = $parameter->name;
        $parameter->delete();
        ParameterService::clearCache($name);

        return response()->json(['success' => true]);
    }

    public function details(Parameter $parameter)
    {
        return response()->json(
            $parameter->details()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function storeDetail(Request $request, Parameter $parameter)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order']
            ?? ((int) $parameter->details()->max('sort_order') + 1);

        $detail = $parameter->details()->create($data);
        ParameterService::clearCache($parameter->name);

        return response()->json($detail, 201);
    }

    public function updateDetail(Request $request, Parameter $parameter, ParameterDetail $detail)
    {
        abort_unless($detail->parameter_id === $parameter->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $detail->update($data);
        ParameterService::clearCache($parameter->name);

        return response()->json($detail->fresh());
    }

    public function destroyDetail(Parameter $parameter, ParameterDetail $detail)
    {
        abort_unless($detail->parameter_id === $parameter->id, 404);

        $detail->delete();
        ParameterService::clearCache($parameter->name);

        return response()->json(['success' => true]);
    }
}
