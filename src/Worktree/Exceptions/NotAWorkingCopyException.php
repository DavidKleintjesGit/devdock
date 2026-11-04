<?php

declare(strict_types=1);

namespace Src\Worktree\Exceptions;

use LogicException;

/**
 * A step meant for a working copy was handed the checkout of a project
 * itself, where it would take away the project's own folders.
 */
class NotAWorkingCopyException extends LogicException
{
	public static function at(string $path): self
	{
		return new self(__(':path is de checkout van een project zelf, geen werkkopie. DevDock raakt daar niets aan.', ['path' => $path]));
	}
}
