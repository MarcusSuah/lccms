<?php

namespace App\Models\Leadership;

use App\Enums\Leadership\LeadershipLevel;
use App\Models\Concerns\AuditsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable local leadership titles (chairperson, town chief, block leader …).
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property LeadershipLevel $level
 * @property bool $single_holder
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'title', 'level', 'single_holder', 'is_active', 'sort_order'])]
class LeadershipPosition extends Model
{
    use AuditsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => LeadershipLevel::class,
            'single_holder' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LeadershipTenure, $this>
     */
    public function tenures(): HasMany
    {
        return $this->hasMany(LeadershipTenure::class, 'position_id');
    }
}
