<?php

namespace App\Actions\Leadership;

use App\Enums\Access\Permission;
use App\Enums\Leadership\LeadershipLevel;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Models\Leadership\LeadershipPosition;
use App\Models\Leadership\LeadershipTenure;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records leadership tenures without ever deleting history.
 *
 * For single-holder positions, tenures for the same position and area may
 * not overlap. When a new leader takes office the previous open tenure can be
 * closed automatically the day before. Any other overlap requires an explicit
 * exception recorded by someone allowed to verify leadership.
 */
class RecordLeadershipTenure
{
    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function handle(User $actor, array $data, ?LeadershipTenure $tenure = null, ?UploadedFile $document = null): LeadershipTenure
    {
        return DB::transaction(function () use ($actor, $data, $tenure, $document): LeadershipTenure {
            $position = LeadershipPosition::query()->findOrFail($data['position_id']);
            [$community, $block] = $this->area($position, $data);

            $startsOn = Carbon::parse($data['starts_on'])->startOfDay();
            $endsOn = isset($data['ends_on']) ? Carbon::parse($data['ends_on'])->startOfDay() : null;
            $exceptionNote = trim((string) ($data['exception_note'] ?? '')) ?: null;

            if ($position->single_holder) {
                $overlaps = LeadershipTenure::query()
                    ->where('position_id', $position->id)
                    ->where('community_id', $community->id)
                    ->where('block_id', $block?->id)
                    ->when($tenure !== null, fn ($query) => $query->whereKeyNot($tenure->id))
                    ->lockForUpdate()
                    ->get()
                    ->filter(fn (LeadershipTenure $existing): bool => $this->overlaps($existing, $startsOn, $endsOn));

                foreach ($overlaps as $existing) {
                    $canClose = ! empty($data['close_previous'])
                        && $existing->ends_on === null
                        && $existing->starts_on->lt($startsOn);

                    if ($canClose) {
                        $existing->update(['ends_on' => $startsOn->copy()->subDay()->toDateString()]);

                        continue;
                    }

                    if ($exceptionNote === null) {
                        throw ValidationException::withMessages([
                            'starts_on' => "This overlaps the tenure of {$existing->holder_name} ({$existing->starts_on->toDateString()} – ".($existing->ends_on?->toDateString() ?? 'present').'). Close that tenure, or record an authorised exception.',
                        ]);
                    }

                    if (! $actor->can(Permission::LeadershipVerify->value)) {
                        throw ValidationException::withMessages(['exception_note' => 'Only staff who verify leadership may authorise overlapping tenures.']);
                    }
                }
            }

            $tenure ??= new LeadershipTenure;
            $tenure->fill(Arr::only($data, ['holder_name', 'contact_phone', 'starts_on', 'ends_on', 'selection_method', 'verification_status', 'is_public', 'notes']));
            $tenure->forceFill([
                ...$community->pathAttributes(),
                'position_id' => $position->id,
                'level' => $position->level,
                'block_id' => $block?->id,
                'exception_note' => $exceptionNote,
                'exception_approved_by' => $exceptionNote !== null ? $actor->id : null,
            ]);

            if ($document !== null) {
                $tenure->forceFill([
                    'document_path' => $document->storeAs('leadership', Str::uuid().'.'.$document->extension(), 'local'),
                    'document_name' => Str::limit(basename($document->getClientOriginalName()), 200, ''),
                ]);
            }

            $tenure->save();

            return $tenure;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Community, 1: Block|null}
     */
    private function area(LeadershipPosition $position, array $data): array
    {
        if ($position->level === LeadershipLevel::Block) {
            $block = Block::query()->find($data['block_id'] ?? 0);

            if ($block === null) {
                throw ValidationException::withMessages(['block_id' => 'This position is held at block level. Choose a block.']);
            }

            return [Community::query()->findOrFail($block->community_id), $block];
        }

        return [Community::query()->findOrFail($data['community_id']), null];
    }

    private function overlaps(LeadershipTenure $existing, Carbon $startsOn, ?Carbon $endsOn): bool
    {
        $existingEnd = $existing->ends_on;

        $startsBeforeOtherEnds = $existingEnd === null || $startsOn->lte($existingEnd);
        $endsAfterOtherStarts = $endsOn === null || $endsOn->gte($existing->starts_on);

        return $startsBeforeOtherEnds && $endsAfterOtherStarts;
    }
}
