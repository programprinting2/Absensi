<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\ParameterDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ParameterService
{
    public static function getByName(string $name): ?Parameter
    {
        $id = Cache::remember("parameter.{$name}.id", 3600, function () use ($name) {
            return Parameter::where('name', $name)->value('id');
        });

        return $id ? Parameter::find($id) : null;
    }

    public static function details(string $parameterName, bool $activeOnly = true): Collection
    {
        $key = 'parameter.'.$parameterName.'.details.'.($activeOnly ? 'active' : 'all');

        $ids = Cache::remember($key, 3600, function () use ($parameterName, $activeOnly) {
            $parameter = self::getByName($parameterName);

            if (! $parameter) {
                return [];
            }

            $query = $parameter->details()->orderBy('sort_order')->orderBy('name');

            if ($activeOnly) {
                $query->active();
            }

            return $query->pluck('id')->all();
        });

        if ($ids === []) {
            return new Collection;
        }

        return ParameterDetail::query()
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Load multiple parameter option lists in one query (cached as plain arrays).
     *
     * @param  array<int, string>  $names
     * @return array<string, array{id: ?string, options: array<int, array{id: string, label: string, value: string}>}>
     */
    public static function optionGroups(array $names): array
    {
        $cacheKey = 'parameter.option_groups.'.md5(implode('|', $names));

        return Cache::remember($cacheKey, 3600, function () use ($names) {
            $parameters = Parameter::query()
                ->whereIn('name', $names)
                ->with(['details' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name');
                }])
                ->get()
                ->keyBy('name');

            $groups = [];

            foreach ($names as $name) {
                $parameter = $parameters->get($name);
                $groups[$name] = [
                    'id' => $parameter?->id,
                    'options' => $parameter
                        ? $parameter->details->map(fn (ParameterDetail $d) => [
                            'id' => $d->id,
                            'label' => $d->name,
                            'value' => $d->value ?: $d->name,
                        ])->values()->all()
                        : [],
                ];
            }

            return $groups;
        });
    }

    public static function clearCache(?string $parameterName = null): void
    {
        if ($parameterName) {
            Cache::forget("parameter.{$parameterName}.id");
            Cache::forget("parameter.{$parameterName}.details.active");
            Cache::forget("parameter.{$parameterName}.details.all");
            Cache::forget("parameter.{$parameterName}");
        } else {
            Parameter::query()->pluck('name')->each(function (string $name) {
                Cache::forget("parameter.{$name}.id");
                Cache::forget("parameter.{$name}.details.active");
                Cache::forget("parameter.{$name}.details.all");
                Cache::forget("parameter.{$name}");
            });
        }

        // Bust grouped option caches (all variants).
        Cache::forget('parameter.option_groups.'.md5(implode('|', ['JABATAN', 'DEPARTEMEN', 'STATUS PTKP', 'BANK'])));
    }
}
