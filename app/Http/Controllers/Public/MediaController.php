<?php

namespace App\Http\Controllers\Public;

use App\Enums\Access\Permission;
use App\Http\Controllers\Controller;
use App\Models\Content\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams photographs from private storage. The public receives only
 * approved, public, consent-cleared images; content staff can preview pending ones.
 */
class MediaController extends Controller
{
    public function __invoke(Request $request, MediaItem $media, string $variant = 'full'): StreamedResponse
    {
        abort_unless(in_array($variant, ['full', 'thumb'], true), 404);

        $public = $media->isPubliclyVisible();
        $staff = $request->user()?->can(Permission::ContentManage->value)
            || $request->user()?->can(Permission::MediaApprove->value);

        abort_unless($public || $staff, 404);

        $path = $variant === 'thumb' ? $media->thumbnail_path : $media->path;
        abort_unless(Storage::disk($media->disk)->exists($path), 404);

        return Storage::disk($media->disk)->response($path, null, [
            'Content-Type' => $media->mime,
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
