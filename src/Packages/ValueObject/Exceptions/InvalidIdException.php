<?php

declare(strict_types=1);

namespace Src\Packages\ValueObject\Exceptions;

use Exception;

/**
 * An identifier was constructed without an id and without a uuid, so it points at nothing.
 */
class InvalidIdException extends Exception
{
	protected $message = 'An id value object needs either an id or a uuid.';
}
