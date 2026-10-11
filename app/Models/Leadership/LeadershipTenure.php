<?php

namespace App\Models\Leadership;

use App\Enums\Leadership\LeadershipLevel;
use App\Enums\Leadership\SelectionMethod;
use App\Enums\VerificationStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\TracksAuthors;
use App\Models\User;
use Database\Factories\Leadership\LeadershipTenureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's period in a leadership position for one community or block.
 *
 * @property int $id
 * @property int $position_id
 * @property LeadershipLevel $level
 * @property int $community_id
 * @property int|null $block_id
 * @property string $holder_name
 * @property string|null $contact_phone
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property SelectionMethod $selection_method
 * @property VerificationStatus $verification_status
 * @property bool $is_public
 * @property string|null $exception_note
 * @property int|null $exception_approved_by
 * @property string|null $document_path
 * @property string|null $document_name
 * @property string|null $notes
 */
#[Fillable(['holder_name', 'contact_phone', 'starts_on', 'ends_on', 'selection_method', 'verification_status', 'is_public', 'notes'])]
#[UseFactory(LeadershipTenureFactory::class)]
class LeadershipTenure extends Model
{
    use AuditsActivity, BelongsToGeoPath, GeoScoped, HasFactory, TracksAuthors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'verification_status' => 'unverified',
        'is_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => LeadershipLevel::class,
            'selection_method' => SelectionMethod::class,
            'verification_status' => VerificationStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_public' => 'boolean',
            'contact_phone' => 'encrypted',
        ];
    }

    /**
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['contact_phone'];
    }

    public function isCurrent(): bool
    {
        return $this->ends_on === null || $this->ends_on->isFuture() || $this->ends_on->isToday();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereDate('starts_on', '<=', now()->toDateString())
            ->where(fn (Builder $inner) => $inner->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString()));
    }

    /**
     * @return BelongsTo<LeadershipPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(LeadershipPosition::class, 'position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function exceptionApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exception_approved_by');
    }
}
