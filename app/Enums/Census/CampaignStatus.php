<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Lifecycle of a census campaign.
 */
enum CampaignStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Planned = 'planned';
    case Active = 'active';
    case InReview = 'in_review';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planned => 'Planned',
            self::Active => 'Active (field work)',
            self::InReview => 'In review',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }

    /**
     * Field data may only be captured or synchronised while a campaign is active.
     */
    public function acceptsFieldData(): bool
    {
        return $this === self::Active;
    }

    /**
     * Supervisors may verify and approve while field work or review is under way.
     */
    public function acceptsReview(): bool
    {
        return in_array($this, [self::Active, self::InReview], true);
    }

    /**
     * Closed and archived campaigns are immutable historical results.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Closed, self::Archived], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Planned, self::Active],
            self::Planned => [self::Draft, self::Active],
            self::Active => [self::InReview],
            self::InReview => [self::Active, self::Closed],
            self::Closed => [self::Archived],
            self::Archived => [],
        };
    }
}
