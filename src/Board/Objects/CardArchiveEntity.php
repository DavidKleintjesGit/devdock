<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A task whose local work was cleaned up, and perhaps taken off the board.
 * It remembers where the work stood, so the task can be opened again.
 */
class CardArchiveEntity extends ValueObject
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $project,
		public readonly ?string $branch,
		public readonly ?string $agentSessionUuid,
		public readonly ?CarbonImmutable $cleanedUpAt,
		public readonly ?CarbonImmutable $archivedAt,
		public readonly ?string $resurfaceSignature,
	) {
		parent::__construct();
	}

	/**
	 * Whether the card stays off the board: it was archived and nothing
	 * happened on its ticket or pull request since.
	 */
	public function keepsOffBoard(string $currentSignature): bool
	{
		return $this->archivedAt !== null && $this->resurfaceSignature === $currentSignature;
	}
}
