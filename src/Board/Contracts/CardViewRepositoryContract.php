<?php

declare(strict_types=1);

namespace Src\Board\Contracts;

use Carbon\CarbonImmutable;
use Src\Board\Enums\UnreadSourceEnum;

interface CardViewRepositoryContract
{
	/**
	 * Notes that you looked at one tab of the card now.
	 */
	public function markSeen(string $card, UnreadSourceEnum $source): void;

	/**
	 * When you last looked at each tab of each card, by card id and by the
	 * value of the source.
	 *
	 * @return array<string, array<string, CarbonImmutable>>
	 */
	public function getSeenAt(): array;

	/**
	 * The moment before which everything counts as read, also on a card you
	 * never opened.
	 */
	public function getReadUntil(): CarbonImmutable;
}
