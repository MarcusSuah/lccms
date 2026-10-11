<?php

namespace App\Actions\Geography;

use App\Enums\Geography\GeoLevel;
use App\Enums\Geography\ReassignmentStatus;
use App\Models\Geography\GeoReassignment;
use App\Models\User;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies an approved reassignment: the area moves to its new parent and the
 * denormalised ancestor columns are rewritten on every live dependent row.
 *
 * Census rows belonging to closed or archived campaigns are deliberately left
 * untouched so approved historical results never shift.
 */
class ApplyGeoReassignment
{
    /**
     * Tables that hold the live register and therefore follow the move.
     *
     * @var list<string>
     */
    public const LIVE_TABLES = ['houses', 'facilities', 'infrastructure_assets', 'leadership_tenures', 'user_geo_scopes'];

    /**
     * Census tables that follow the move only for campaigns still in progress.
     *
     * @var list<string>
     */
    public const CAMPAIGN_TABLES = ['house_enumerations', 'households', 'occupants'];

    public function __construct(private GeoAreaResolver $resolver) {}

    public function approve(GeoReassignment $reassignment, User $approver, ?string $note): GeoReassignment
    {
        if ($reassignment->requested_by === $approver->id) {
            throw ValidationException::withMessages(['decision' => 'A reassignment must be approved by someone other than the requester.']);
        }

        return DB::transaction(function () use ($reassignment, $approver, $note): GeoReassignment {
            $reassignment = GeoReassignment::query()->lockForUpdate()->findOrFail($reassignment->id);

            if ($reassignment->status !== ReassignmentStatus::Pending) {
                throw ValidationException::withMessages(['decision' => 'This reassignment has already been decided.']);
            }

            $level = $reassignment->level;
            $area = $this->resolver->find($level, $reassignment->area_id);
            $newParent = $this->resolver->find($level->parent(), $reassignment->to_parent_id);

            if ($area === null || $newParent === null) {
                throw ValidationException::withMessages(['decision' => 'The area or its new parent no longer exists.']);
            }

            if ((int) $area->getAttribute($level->parent()->column()) !== $reassignment->from_parent_id) {
                throw ValidationException::withMessages(['decision' => 'The area has moved since this request was made. Submit a new request.']);
            }

            $ancestorPath = $newParent->pathAttributes();
            $counts = [];

            $counts[$level->table()] = DB::table($level->table())
                ->where('id', $area->id)
                ->update([...$ancestorPath, 'updated_at' => now()]);

            foreach ($this->descendantLevels($level) as $descendant) {
                $counts[$descendant->table()] = DB::table($descendant->table())
                    ->where($level->column(), $area->id)
                    ->update($ancestorPath);
            }

            foreach (self::LIVE_TABLES as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $counts[$table] = DB::table($table)->where($level->column(), $area->id)->update($ancestorPath);
                }
            }

            foreach (self::CAMPAIGN_TABLES as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $counts[$table] = DB::table($table)
                        ->where($level->column(), $area->id)
                        ->whereIn('campaign_id', fn ($query) => $query->select('id')->from('census_campaigns')->whereNotIn('status', ['closed', 'archived']))
                        ->update($ancestorPath);
                }
            }

            $reassignment->forceFill([
                'status' => ReassignmentStatus::Applied,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'applied_at' => now(),
                'to_path' => [...$ancestorPath, $level->column() => $area->id],
                'affected_counts' => $counts,
            ])->save();

            activity('geography')
                ->performedOn($area)
                ->causedBy($approver)
                ->event('reassigned')
                ->withProperties(['reassignment_id' => $reassignment->id, 'from' => $reassignment->from_path, 'to' => $reassignment->to_path])
                ->log('reassigned');

            return $reassignment;
        });
    }

    public function reject(GeoReassignment $reassignment, User $decider, string $note): GeoReassignment
    {
        if ($reassignment->status !== ReassignmentStatus::Pending) {
            throw ValidationException::withMessages(['decision' => 'This reassignment has already been decided.']);
        }

        $reassignment->forceFill([
            'status' => ReassignmentStatus::Rejected,
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        return $reassignment;
    }

    /**
     * @return list<GeoLevel>
     */
    private function descendantLevels(GeoLevel $level): array
    {
        return array_slice(GeoLevel::cases(), $level->depth() + 1);
    }
}
