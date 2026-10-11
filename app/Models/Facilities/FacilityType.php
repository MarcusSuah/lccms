<?php

namespace App\Models\Facilities;

use App\Enums\Facilities\FacilityCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable facility types (e.g. "Primary school") grouped into the fixed
 * reporting categories.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property FacilityCategory $category
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'category', 'is_active', 'sort_order'])]
class FacilityType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FacilityCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Facility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
