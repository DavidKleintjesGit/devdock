<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides the column of a task: how far its work got, from a to-do
 * nobody touched to a merged pull request.
 */
final class BoardColumnFactsObject extends ValueObject
{
	public function __construct(
		public readonly bool $isTickedOff,
		public readonly bool $hasPullRequest,
		public readonly bool $hasOpenPullRequest,
		public readonly bool $hasReviewRound,
		public readonly bool $hasWorkingCopy,
		public readonly bool $isStarted,
		public readonly bool $isForLater,
	) {
		parent::__construct();
	}
}
