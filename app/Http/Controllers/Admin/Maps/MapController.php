<?php

namespace App\Http\Controllers\Admin\Maps;

use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Maps\MapLayers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff map. Points are loaded per layer so the page only fetches what the
 * user switches on, and every layer is filtered by permission and reach.
 */
class MapController extends Controller
{
    public function index(Request $request, MapLayers $layers): Response
    {
        $available = $layers->availableTo($request->user());
        abort_if($available === [], 403);

        return Inertia::render('admin/map', [
            'layers' => $available,
            'tiles' => config('lccms.maps'),
        ]);
    }

    public function points(Request $request, MapLayers $layers, GeoAreaResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'layer' => ['required', 'string', Rule::in(['communities', 'facilities', 'infrastructure', 'houses'])],
            'level' => ['nullable', Rule::enum(GeoLevel::class)],
            'area_id' => ['nullable', 'required_with:level', 'integer'],
        ]);

        $path = [];

        if (isset($validated['level'])) {
            $level = GeoLevel::from($validated['level']);
            abort_if($resolver->find($level, (int) $validated['area_id']) === null, 404);
            $path = [$level->column() => (int) $validated['area_id']];
        }

        return response()->json($layers->staffPoints($request->user(), $validated['layer'], $path));
    }
}
