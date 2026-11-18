<?php

declare(strict_types=1);

namespace Src\Board\Contracts;

use Src\Board\Objects\CardArchiveEntity;
use Src\Board\Objects\CardArchiveEntityCollection;

interface CardArchiveRepositoryContract
{
	public function getAll(): CardArchiveEntityCollection;

	public function getFor(string $card): ?CardArchiveEntity;

	/**
	 * Notes that the local work of a task was cleaned up, with where it stood.
	 */
	public function markCleanedUp(string $card, ?string $project, ?string $branch, ?string $agentSessionUuid): CardArchiveEntity;

	/**
	 * Takes a card off the board until its ticket or pull request changes away
	 * from the given signature.
	 */
	public function archive(string $card, string $resurfaceSignature): CardArchiveEntity;

	/**
	 * Forgets everything noted about a card, so it stands on the board as any other.
	 */
	public function forget(string $card): void;
}
