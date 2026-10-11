<?php

namespace App\Services\Sync;

use RuntimeException;

/**
 * The operation references a parent the server has not received yet.
 * The device keeps the operation and retries after the parent syncs.
 */
class ParentPendingException extends RuntimeException {}
