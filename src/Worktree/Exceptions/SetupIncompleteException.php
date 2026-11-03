<?php

declare(strict_types=1);

namespace Src\Worktree\Exceptions;

use Exception;
use Src\Worktree\Objects\ReadinessObject;
use Src\Worktree\Objects\SetupCheckObject;

/**
 * The working copy is not complete, so no agent or lens may work in it yet.
 */
class SetupIncompleteException extends Exception
{
	public function __construct(
		public readonly ReadinessObject $readinessObject,
	) {
		$openChecks = $readinessObject->openChecks();

		parent::__construct(__('De werkkopie is nog niet gereed: :points. De agent en de lenzen wachten tot het op de tab Werkkopie in orde is.', [
			'points' => implode('; ', array_map(
				fn (SetupCheckObject $setupCheckObject): string => $setupCheckObject->setupCheck->label() . ' (' . $setupCheckObject->detail . ')',
				$openChecks,
			)),
		]));
	}
}
