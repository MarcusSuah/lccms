<?php

namespace App\Models\Access;

use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\HasAreaPath;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A geographic area a user is allowed to act on. The deepest non-null
 * column determines the scope level.
 *
 * @property int $id
 * @property int $user_id
 * @property int $county_id
 * @property int|null $district_id
 * @property int|null $clan_id
 * @property int|null $town_id
 * @property int|null $community_id
 * @property int|null $block_id
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $granted_by
 */
#[Fillable(['county_id', 'district_id', 'clan_id', 'town_id', 'community_id', 'block_id', 'starts_on', 'ends_on', 'granted_by'])]
class UserGeoScope extends Model
{
    use AuditsActivity, GeoScoped, HasAreaPath;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
