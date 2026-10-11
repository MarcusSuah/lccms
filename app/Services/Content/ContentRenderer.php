<?php

namespace App\Services\Content;

use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Converts editor Markdown to HTML and sanitises it with HTMLPurifier against a
 * strict allow-list. Raw HTML in the Markdown is escaped first, so the only
 * markup that can reach the page is what Markdown produces and the allow-list keeps.
 */
class ContentRenderer
{
    private const ALLOWED = 'p,br,strong,em,ul,ol,li,a[href|title],h2,h3,h4,blockquote,code,pre,hr,table,thead,tbody,tr,th,td';

    public function render(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return $this->sanitize($html);
    }

    public function sanitize(string $html): string
    {
        return (string) Purifier::clean($html, [
            'HTML.Allowed' => self::ALLOWED,
            'HTML.Nofollow' => true,
            'HTML.TargetBlank' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'AutoFormat.RemoveEmpty' => true,
            'Cache.DefinitionImpl' => null,
        ]);
    }

    /**
     * Plain-text summary for listings and meta descriptions.
     */
    public function excerpt(string $markdown, int $length = 220): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(Str::markdown($markdown, ['html_input' => 'strip']))) ?? ''), $length);
    }
}
