<?php

namespace App\Models\Census;

use App\Models\Concerns\HasAreaPath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One area included in a campaign's geographic coverage.
 *
 * @property int $id
 * @property int $campaign_id
 */
#[Fillable(['county_id', 'district_id', 'clan_id', 'town_id', 'community_id', 'block_id'])]
class CampaignScope extends Model
{
    use HasAreaPath;

    /**
     * @return BelongsTo<CensusCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CensusCampaign::class, 'campaign_id');
    }
}
