<?php

namespace App\Services\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\DuplicateStatus;
use App\Models\Census\DuplicateFlag;
use App\Models\Census\Occupant;
use Illuminate\Support\Facades\DB;

/**
 * Flags possible double registration of the same person within a campaign
 * for human review. It never merges, archives or deletes anything.
 */
class DuplicateDetector
{
    public const FLAG_THRESHOLD = 60;

    private const CANDIDATE_LIMIT = 50;

    /**
     * @return int number of new flags raised
     */
    public function scan(Occupant $occupant): int
    {
        if ($occupant->status === CensusRecordStatus::Archived) {
            return 0;
        }

        $candidates = Occupant::query()
            ->where('campaign_id', $occupant->campaign_id)
            ->where('county_id', $occupant->county_id)
            ->whereKeyNot($occupant->id)
            ->where('status', '!=', CensusRecordStatus::Archived)
            ->where(function ($query) use ($occupant): void {
                $query->where('normalized_name', $occupant->normalized_name)
                    ->orWhereRaw('SOUNDEX(normalized_name) = SOUNDEX(?)', [$occupant->normalized_name]);
            })
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $raised = 0;

        foreach ($candidates as $candidate) {
            [$score, $reasons] = $this->score($occupant, $candidate);

            if ($score < self::FLAG_THRESHOLD) {
                continue;
            }

            $exists = DuplicateFlag::query()
                ->where(fn ($query) => $query->where('occupant_id', $occupant->id)->where('matched_occupant_id', $candidate->id))
                ->orWhere(fn ($query) => $query->where('occupant_id', $candidate->id)->where('matched_occupant_id', $occupant->id))
                ->exists();

            if ($exists) {
                continue;
            }

            DB::transaction(fn () => DuplicateFlag::query()->create([
                'campaign_id' => $occupant->campaign_id,
                'occupant_id' => $occupant->id,
                'matched_occupant_id' => $candidate->id,
                'score' => min(100, $score),
                'reasons' => $reasons,
            ]));

            $raised++;
        }

        return $raised;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    public function score(Occupant $a, Occupant $b): array
    {
        $score = 0;
        $reasons = [];

        if ($a->normalized_name === $b->normalized_name) {
            $score += 50;
            $reasons[] = 'Same name';
        } else {
            $score += 30;
            $reasons[] = 'Similar-sounding name';
        }

        if ($a->date_of_birth !== null && $b->date_of_birth !== null) {
            if ($a->date_of_birth->isSameDay($b->date_of_birth)) {
                $score += 30;
                $reasons[] = 'Same date of birth';
            } else {
                $score -= 30;
            }
        } elseif ($a->age_years !== null && $b->age_years !== null && abs($a->age_years - $b->age_years) <= 1) {
            $score += 15;
            $reasons[] = 'Similar age';
        }

        if ($a->sex === $b->sex) {
            $score += 10;
        } else {
            $score -= 40;
        }

        if ($a->community_id === $b->community_id) {
            $score += 10;
            $reasons[] = 'Same community';
        }

        if ($a->household_id === $b->household_id) {
            $score += 10;
            $reasons[] = 'Same household';
        }

        return [$score, $reasons];
    }

    public static function openCount(int $campaignId): int
    {
        return DuplicateFlag::query()
            ->where('campaign_id', $campaignId)
            ->where('status', DuplicateStatus::Open)
            ->count();
    }
}
