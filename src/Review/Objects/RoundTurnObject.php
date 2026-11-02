<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\ReportObject;
use Src\Worktree\Objects\ChangeObject;

/**
 * What the agent did with the findings of one round: its turn, the report it
 * closed with, and the files it changed on the way to the next round.
 */
class RoundTurnObject extends ValueObject
{
	/**
	 * @param  array<int, ChangeObject>  $changes
	 */
	public function __construct(
		public readonly PromptRunEntity $run,
		public readonly ?ReportObject $report,
		public readonly array $changes,
	) {
		parent::__construct();
	}

	public function addedLines(): int
	{
		return array_sum(array_map(fn (ChangeObject $changeObject) => $changeObject->added, $this->changes));
	}

	public function removedLines(): int
	{
		return array_sum(array_map(fn (ChangeObject $changeObject) => $changeObject->removed, $this->changes));
	}
}
