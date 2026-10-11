<?php

namespace App\Services\Geography;

use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;
use Illuminate\Support\Collection;

/**
 * Flags likely duplicate place names among siblings (same parent) so that a
 * human can confirm before a second record is created. Exact matches on the
 * normalised name are blocked by a unique index; this catches spelling
 * variants such as "Westpoint" and "West Point".
 */
class DuplicateNameDetector
{
    private const SIMILARITY_THRESHOLD = 0.85;

    /**
     * @return Collection<int, GeoArea>
     */
    public function similarSiblings(GeoLevel $level, ?int $parentId, string $name, ?int $ignoreId = null): Collection
    {
        $class = $level->modelClass();
        $candidate = GeoArea::normalizeName($name);
        $compactCandidate = str_replace(' ', '', $candidate);

        return $class::query()
            ->when($level->parent() !== null, fn ($query) => $query->where($level->parent()->column(), $parentId))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->select(['id', 'name', 'normalized_name', 'reference_code'])
            ->orderBy('id')
            ->lazy(500)
            ->filter(function (GeoArea $sibling) use ($candidate, $compactCandidate): bool {
                $existing = $sibling->normalized_name;
                $compactExisting = str_replace(' ', '', $existing);

                if ($existing === $candidate || $compactExisting === $compactCandidate) {
                    return true;
                }

                if (metaphone($compactExisting) === metaphone($compactCandidate) && $compactCandidate !== '') {
                    return true;
                }

                $longest = max(mb_strlen($compactExisting), mb_strlen($compactCandidate), 1);

                return 1 - (levenshtein($compactExisting, $compactCandidate) / $longest) >= self::SIMILARITY_THRESHOLD;
            })
            ->take(10)
            ->values()
            ->collect();
    }
}
