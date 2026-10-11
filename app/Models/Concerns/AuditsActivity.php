<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records create, update and delete events in the activity log.
 *
 * Attributes listed by auditRedactedAttributes() are still recorded as having
 * changed, but their values are replaced so personal data never enters the log.
 */
trait AuditsActivity
{
    use LogsActivity;

    public const REDACTED = '[redacted]';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }

    /**
     * Attributes whose values must never be written to the activity log.
     *
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return [];
    }

    public function beforeActivityLogged(Model $activity, string $eventName): void
    {
        $redacted = $this->auditRedactedAttributes();

        if ($redacted === [] || $activity->attribute_changes === null) {
            return;
        }

        $activity->attribute_changes = $activity->attribute_changes->map(
            function (mixed $values): mixed {
                if (! is_array($values)) {
                    return $values;
                }

                foreach ($this->auditRedactedAttributes() as $attribute) {
                    if (array_key_exists($attribute, $values)) {
                        $values[$attribute] = self::REDACTED;
                    }
                }

                return $values;
            },
        );
    }
}
