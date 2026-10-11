<?php

namespace App\Http\Controllers\Public;

use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public area names for the complaint form's location picker. Only active
 * registry names and codes are returned: no coordinates or other detail.
 */
class AreaOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::enum(GeoLevel::class)],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $level = GeoLevel::from($validated['level']);
        $parent = $level->parent();

        abort_if($parent !== null && empty($validated['parent_id']), 422, 'A parent is required for this level.');

        $options = $level->modelClass()::query()
            ->active()
            ->when($parent !== null, fn ($query) => $query->where($parent->column(), $validated['parent_id']))
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'reference_code'])
            ->map(fn ($area): array => ['value' => $area->id, 'label' => $area->name, 'code' => $area->reference_code]);

        return response()->json(['data' => $options]);
    }
}
