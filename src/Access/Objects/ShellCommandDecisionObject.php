<?php

declare(strict_types=1);

namespace Src\Access\Objects;

use Src\Access\Enums\ShellCommandEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * Whether a shell command may run, and the git command that kept it back.
 */
class ShellCommandDecisionObject extends ValueObject
{
	public function __construct(
		public readonly ShellCommandEnum $outcome,
		public readonly ?string $gitCommand,
	) {
		parent::__construct();
	}

	public function isAllowed(): bool
	{
		return $this->outcome === ShellCommandEnum::ALLOWED;
	}

	/**
	 * Why the command is refused, as the agent reads it; null when it may run.
	 */
	public function reason(): ?string
	{
		return match ($this->outcome) {
			ShellCommandEnum::ALLOWED => null,
			ShellCommandEnum::WRITES_GIT => __('DevDock laat git alleen lezen: git :command is voor de knoppen. Committen, pushen en rebasen doet David in DevDock. Laat de wijziging in de werkkopie staan en zeg in je verslag wat klaar is.', [
				'command' => $this->gitCommand,
			]),
		};
	}
}
