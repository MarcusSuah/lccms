<?php

namespace App\Enums\Content;

use App\Enums\Concerns\HasOptions;

/**
 * Editorial workflow for news and blog posts.
 */
enum PostStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
