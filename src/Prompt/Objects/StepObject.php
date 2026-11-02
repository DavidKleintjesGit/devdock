<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One thing the agent reached for while it worked: the tool, and what it
 * pointed that tool at.
 */
class StepObject extends ValueObject
{
	public function __construct(
		public readonly string $tool,
		public readonly string $subject,
		public readonly int $added = 0,
		public readonly int $removed = 0,
	) {
		parent::__construct();
	}

	public function readsFiles(): bool
	{
		return in_array($this->tool, ['Read', 'Grep', 'Glob', 'LS'], true);
	}

	public function changesFiles(): bool
	{
		return in_array($this->tool, ['Edit', 'Write', 'MultiEdit', 'NotebookEdit'], true);
	}

	public function runsCommand(): bool
	{
		return in_array($this->tool, ['Bash', 'PowerShell'], true);
	}
}
