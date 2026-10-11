<?php

namespace App\Models\Content;

use App\Models\Concerns\AuditsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Editable static pages (about, methodology, governance, data protection).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body_markdown
 * @property string $body_html
 * @property bool $is_published
 */
#[Fillable(['title', 'body_markdown', 'is_published'])]
class Page extends Model
{
    use AuditsActivity;

    public const EDITABLE = [
        'about' => 'About the project',
        'methodology' => 'Census methodology',
        'governance' => 'Governance and responsible contacts',
        'data-protection' => 'Data protection',
        'contact' => 'Contact information',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
