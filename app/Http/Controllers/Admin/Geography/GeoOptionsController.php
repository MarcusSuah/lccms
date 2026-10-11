<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Supplies options for cascading geographic selectors. Results are limited to
 * the areas on the path to, or beneath, the user's own assignments unless the
 * caller asks for the unrestricted registry (geography browsing only).
 */
class GeoOptionsController extends Controller
{
    public function __invoke(Request $request, GeoScope $geoScope): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::enum(GeoLevel::class)],
            'parent_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['assigned', 'registry'])],
        ]);

        $level = GeoLevel::from($validated['level']);
        $parentLevel = $level->parent();

        abort_if($parentLevel !== null && empty($validated['parent_id']) && empty($validated['search']), 422, 'A parent is required for this level.');

        $class = $level->modelClass();

        $query = $class::query()
            ->active()
            ->when($parentLevel !== null && ! empty($validated['parent_id']), fn (Builder $query) => $query->where($parentLevel->column(), $validated['parent_id']))
            ->search($validated['search'] ?? null);

        if (($validated['scope'] ?? 'assigned') === 'assigned') {
            $geoScope->applyToLevel($query, $level, $request->user());
        }

        $options = $query
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'reference_code'])
            ->map(fn ($area): array => [
                'value' => $area->id,
                'label' => $area->name,
                'code' => $area->reference_code,
            ]);

        return response()->json(['data' => $options]);
    }
}
