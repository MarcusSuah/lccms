<?php

namespace App\Actions\Facilities;

use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityType;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Services\Facilities\FacilityDetailRules;
use App\Services\SequenceCodes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a facility. The reporting category always follows the
 * chosen type, and the geographic path is copied from the community/block.
 */
class SaveFacility
{
    public function __construct(private SequenceCodes $codes) {}

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function handle(array $data, ?Facility $facility = null): Facility
    {
        return DB::transaction(function () use ($data, $facility): Facility {
            $type = FacilityType::query()->findOrFail($data['facility_type_id']);
            $community = Community::query()->findOrFail($data['community_id']);
            $block = null;

            if (! empty($data['block_id'])) {
                $block = Block::query()->findOrFail($data['block_id']);

                if ($block->community_id !== $community->id) {
                    throw ValidationException::withMessages(['block_id' => 'The block is not in the selected community.']);
                }
            }

            $facility ??= new Facility;
            $attributes = Arr::except($data, ['community_id', 'block_id', 'details']);
            $attributes['details'] = FacilityDetailRules::only($type->category, $data['details'] ?? null);

            $facility->fill($attributes);
            $facility->forceFill([
                ...$community->pathAttributes(),
                'block_id' => $block?->id,
                'category' => $type->category,
            ]);

            if (! $facility->exists) {
                $facility->reference_code = $this->codes->next(Facility::class, $community->reference_code.'-F');
            }

            $facility->save();

            return $facility;
        });
    }
}
