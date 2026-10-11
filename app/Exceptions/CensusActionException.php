<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A census operation that is not allowed in the record's current state or for
 * the acting user. Web requests render it as a 403/422; the synchronisation
 * API reports it per item using the machine-readable reason.
 */
class CensusActionException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const CAMPAIGN_NOT_ACCEPTING = 'campaign_not_accepting';

    public const NOT_EDITABLE = 'not_editable';

    public const INVALID_PARENT = 'invalid_parent';

    public const INCOMPLETE = 'incomplete';

    public const INVALID_TRANSITION = 'invalid_transition';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function unauthorized(string $message = 'You are not assigned to this area for this campaign.'): self
    {
        return new self(self::UNAUTHORIZED, $message);
    }

    public function status(): int
    {
        return $this->reason === self::UNAUTHORIZED ? 403 : 422;
    }
}
