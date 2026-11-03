<?php

declare(strict_types=1);

namespace Src\Board\Contracts;

use Carbon\CarbonImmutable;

interface CardViewRepositoryContract
{
	/**
	 * Notes that you looked at the card now.
	 */
	public function markSeen(string $card): void;

	/**
	 * When you last looked at each card, by card id.
	 *
	 * @return array<string, CarbonImmutable>
	 */
	public function getSeenAt(): array;

	/**
	 * The moment before which everything counts as read, also on a card you
	 * never opened.
	 */
	public function getReadUntil(): CarbonImmutable;
}
