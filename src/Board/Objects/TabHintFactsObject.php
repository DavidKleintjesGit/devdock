<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\LoopStatusEnum;

/**
 * What a card shows about itself that may need you: a question from the agent,
 * work that is not committed or pushed, a branch that clashes or runs behind,
 * a working copy that is not ready, the review loop and the pull request.
 * Whether the working copy is ready is null when it was not looked at.
 */
final class TabHintFactsObject extends ValueObject
{
	public function __construct(
		public readonly bool $hasUnreadQuestion,
		public readonly bool $hasWorkingCopy,
		public readonly int $uncommittedFiles,
		public readonly int $unpushedCommits,
		public readonly bool $isRebasing,
		public readonly bool $hasConflict,
		public readonly int $behind,
		public readonly ?bool $isReady,
		public readonly ?LoopStatusEnum $loopStatus,
		public readonly int $openFindings,
		public readonly bool $hasChangesRequested,
		public readonly int $unreadRemarks,
	) {
		parent::__construct();
	}
}
