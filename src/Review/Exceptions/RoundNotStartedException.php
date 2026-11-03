<?php

declare(strict_types=1);

namespace Src\Review\Exceptions;

use Exception;

/**
 * A round of the lenses did not open, for a reason a person has to act on.
 */
class RoundNotStartedException extends Exception
{
	public static function nothingChanged(): self
	{
		return new self(__('Er ging geen ronde open: er staat niets gewijzigd in de werkkopie.'));
	}

	public static function maximumReached(int $maxRounds): self
	{
		return new self(__('Er ging geen ronde open: de loop zit op zijn maximum van :max rondes. Start de loop opnieuw.', ['max' => $maxRounds]));
	}
}
