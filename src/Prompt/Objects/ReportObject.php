<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Prompt\Enums\ReportStatusEnum;

/**
 * What a round came back with, in the shape every prompt asks for: where it
 * ended up, what it changed, what is still open and what it wants from you.
 */
class ReportObject extends ValueObject
{
	/**
	 * @param  array<int, string>  $done
	 * @param  array<int, string>  $open
	 */
	public function __construct(
		public readonly ReportStatusEnum $status,
		public readonly string $summary,
		public readonly array $done,
		public readonly array $open,
		public readonly ?string $question,
		public readonly ?CommitMessageObject $commitMessage,
	) {
		parent::__construct();
	}

	/**
	 * Whether the round left changes that are ready to be committed.
	 */
	public function isReadyToCommit(): bool
	{
		return $this->status === ReportStatusEnum::DONE && $this->commitMessage !== null;
	}

	public function needsAnswer(): bool
	{
		return $this->status === ReportStatusEnum::QUESTION && $this->question !== null;
	}
}
