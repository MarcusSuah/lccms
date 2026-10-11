<?php

namespace App\Actions\Complaints;

use App\Enums\Complaints\ComplaintStatus;
use App\Enums\Geography\GeoLevel;
use App\Models\Complaints\Complaint;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Records a public complaint. The plain tracking token is returned once and
 * never stored; only its keyed hash is kept.
 */
class SubmitComplaint
{
    public const MAX_IMAGES = 5;

    public function __construct(
        private GeoAreaResolver $resolver,
        private ImageProcessor $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated
     * @param  list<UploadedFile>  $images
     * @return array{complaint: Complaint, token: string}
     */
    public function handle(array $data, array $images): array
    {
        if (count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => 'You can attach at most '.self::MAX_IMAGES.' images.']);
        }

        $area = $this->resolver->find(GeoLevel::from($data['level']), (int) $data['area_id']);

        if ($area === null) {
            throw ValidationException::withMessages(['area_id' => 'The selected area does not exist.']);
        }

        $reference = $this->newReference();
        $stored = [];

        try {
            foreach (array_values($images) as $index => $image) {
                $stored[] = ['slot' => $index + 1, ...$this->images->store($image, 'complaints/'.$reference)];
            }
        } catch (ValidationException $exception) {
            $this->discard($stored);

            throw ValidationException::withMessages(['images' => $exception->errors()['image'][0] ?? 'An image could not be accepted.']);
        }

        $token = Str::random(48);

        try {
            $complaint = DB::transaction(function () use ($data, $area, $reference, $token, $stored): Complaint {
                $anonymous = (bool) ($data['is_anonymous'] ?? false);
                $complaint = new Complaint(Arr::only($data, ['subject', 'category', 'description', 'house_reference']));
                $complaint->fill([
                    'is_anonymous' => $anonymous,
                    'complainant_name' => $anonymous ? null : ($data['complainant_name'] ?? null),
                    'complainant_phone' => $anonymous ? null : ($data['complainant_phone'] ?? null),
                    'complainant_email' => $anonymous ? null : ($data['complainant_email'] ?? null),
                ]);
                $complaint->forceFill([
                    ...$this->resolver->fullPath($area),
                    'public_reference' => $reference,
                    'tracking_token_hash' => Complaint::hashToken($token),
                    'status' => ComplaintStatus::Submitted,
                ])->save();

                foreach ($stored as $attachment) {
                    $complaint->attachments()->create([...$attachment, 'disk' => 'local']);
                }

                $complaint->history()->create(['from_status' => null, 'to_status' => ComplaintStatus::Submitted]);

                return $complaint;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        return ['complaint' => $complaint, 'token' => $token];
    }

    /**
     * Random, non-sequential public reference such as CMP-26-7KQ4MZ.
     */
    private function newReference(): string
    {
        do {
            $reference = 'CMP-'.now()->format('y').'-'.Str::upper(Str::random(6));
        } while (Complaint::query()->where('public_reference', $reference)->exists());

        return $reference;
    }

    /**
     * @param  list<array<string, mixed>>  $stored
     */
    private function discard(array $stored): void
    {
        foreach ($stored as $file) {
            Storage::disk('local')->delete([$file['path'], $file['thumbnail_path']]);
        }
    }
}
