<?php

namespace App\Models\Complaints;

use App\Enums\Complaints\ComplaintCategory;
use App\Enums\Complaints\ComplaintPriority;
use App\Enums\Complaints\ComplaintStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\GeoScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_reference
 * @property string $tracking_token_hash
 * @property string $subject
 * @property ComplaintCategory $category
 * @property string $description
 * @property int $county_id
 * @property int|null $community_id
 * @property int|null $block_id
 * @property string|null $house_reference
 * @property bool $is_anonymous
 * @property string|null $complainant_name
 * @property string|null $complainant_phone
 * @property string|null $complainant_email
 * @property ComplaintStatus $status
 * @property ComplaintPriority $priority
 * @property int|null $assigned_to
 * @property string|null $resolution_summary
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 */
#[Fillable(['subject', 'category', 'description', 'house_reference', 'is_anonymous', 'complainant_name', 'complainant_phone', 'complainant_email'])]
#[Hidden(['tracking_token_hash'])]
class Complaint extends Model
{
    use AuditsActivity, BelongsToGeoPath, GeoScoped;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
        'priority' => 'normal',
        'is_anonymous' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'priority' => ComplaintPriority::class,
            'is_anonymous' => 'boolean',
            'complainant_name' => 'encrypted',
            'complainant_phone' => 'encrypted',
            'complainant_email' => 'encrypted',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['complainant_name', 'complainant_phone', 'complainant_email', 'description'];
    }

    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_reference';
    }

    /**
     * @return HasMany<ComplaintAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class)->orderBy('slot');
    }

    /**
     * @return HasMany<ComplaintStatusChange, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(ComplaintStatusChange::class)->orderBy('id');
    }

    /**
     * @return HasMany<ComplaintNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
