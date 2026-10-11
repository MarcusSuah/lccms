<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Turns an uploaded photograph into safe, stored images.
 *
 *  - The real content type is sniffed (never the client name or header).
 *  - Pixel dimensions are checked before decoding to refuse decompression bombs.
 *  - The image is fully decoded and re-encoded as WebP. Re-encoding discards
 *    EXIF/GPS metadata and any payload appended to the file.
 *  - Files get random names on private storage; nothing is web-accessible directly.
 */
class ImageProcessor
{
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{path: string, thumbnail_path: string, mime: string, size: int, width: int, height: int, sha256: string, original_name: string}
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'local'): array
    {
        $realPath = $file->getRealPath();
        $mime = $realPath ? (new \finfo(FILEINFO_MIME_TYPE))->file($realPath) : false;

        if ($realPath === false || ! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['image' => 'Only JPEG, PNG or WebP photographs are accepted.']);
        }

        $dimensions = @getimagesize($realPath);

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw ValidationException::withMessages(['image' => 'The file is not a readable image.']);
        }

        if (config('lccms.uploads.image_max_pixels') < $dimensions[0] * $dimensions[1]) {
            throw ValidationException::withMessages(['image' => 'The image dimensions are too large.']);
        }

        try {
            $manager = ImageManager::usingDriver(Driver::class);
            $image = $manager->decodePath($realPath);

            try {
                $image->orient();
            } catch (Throwable) {
                // Orientation metadata is optional; continue without rotation.
            }

            $maximum = (int) config('lccms.uploads.image_max_dimension');
            $full = (clone $image)->scaleDown($maximum, $maximum)->encode(new WebpEncoder(quality: 82, strip: true));
            $thumbnail = $image->scaleDown(480, 480)->encode(new WebpEncoder(quality: 75, strip: true));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['image' => 'The image could not be processed.']);
        }

        $name = Str::uuid()->toString();
        $path = "{$directory}/{$name}.webp";
        $thumbnailPath = "{$directory}/{$name}-thumb.webp";
        $binary = $full->toString();

        Storage::disk($disk)->put($path, $binary);
        Storage::disk($disk)->put($thumbnailPath, $thumbnail->toString());

        $stored = @getimagesizefromstring($binary) ?: [0, 0];

        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime' => 'image/webp',
            'size' => strlen($binary),
            'width' => (int) $stored[0],
            'height' => (int) $stored[1],
            'sha256' => hash('sha256', $binary),
            'original_name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', basename($file->getClientOriginalName())) ?? 'image', 200, ''),
        ];
    }
}
