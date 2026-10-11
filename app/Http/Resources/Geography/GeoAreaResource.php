<?php

namespace App\Http\Resources\Geography;

use App\Models\Geography\Community;
use App\Models\Geography\District;
use App\Models\Geography\GeoArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GeoArea
 */
class GeoAreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GeoArea $area */
        $area = $this->resource;

        return [
            'id' => $area->id,
            'level' => $area::level()->value,
            'reference_code' => $area->reference_code,
            'name' => $area->name,
            'description' => $area->description,
            'status' => $area->status->value,
            'verification_status' => $area->verification_status->value,
            'latitude' => $area->latitude,
            'longitude' => $area->longitude,
            'has_boundary' => $area->boundary !== null,
            'boundary' => $this->when($request->routeIs('admin.geography.edit'), fn () => $area->boundary === null ? null : json_encode($area->boundary)),
            'is_fixture' => $area->is_fixture,
            'district_number' => $this->when($area instanceof District, fn () => $area->district_number),
            'slug' => $this->when($area instanceof Community, fn () => $area->slug),
            'history' => $this->when($area instanceof Community, fn () => $area->history),
            'public_email' => $this->when($area instanceof Community, fn () => $area->public_email),
            'public_phone' => $this->when($area instanceof Community, fn () => $area->public_phone),
            'is_published' => $this->when($area instanceof Community, fn () => $area->is_published),
            'children_count' => $this->whenCounted('children'),
            'breadcrumb' => $this->when(
                collect($area::ancestorRelations())->every(fn (string $relation): bool => $area->relationLoaded($relation)),
                fn () => array_map(fn (GeoArea $crumb): array => [
                    'id' => $crumb->id,
                    'level' => $crumb::level()->value,
                    'name' => $crumb->name,
                    'reference_code' => $crumb->reference_code,
                ], $area->breadcrumb()),
            ),
            'updated_at' => $area->updated_at?->toIso8601String(),
        ];
    }
}
