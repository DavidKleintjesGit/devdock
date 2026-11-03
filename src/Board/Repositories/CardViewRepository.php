<?php

declare(strict_types=1);

namespace Src\Board\Repositories;

use Carbon\CarbonImmutable;
use Src\Board\Contracts\CardViewRepositoryContract;
use Src\Board\Enums\UnreadSourceEnum;
use Src\Board\Models\CardView as CardViewEloquent;

class CardViewRepository implements CardViewRepositoryContract
{
	/**
	 * The row that holds when the badges arrived, set by its migration.
	 */
	private const READ_UNTIL_CARD = '*';

	/**
	 * Splits the card from the tab in a stored key: CSM-3560@Agent.
	 */
	private const TAB_SEPARATOR = '@';

	public function markSeen(string $card, UnreadSourceEnum $source): void
	{
		CardViewEloquent::query()->updateOrCreate(
			['card' => $card . self::TAB_SEPARATOR . $source->value],
			['seen_at' => CarbonImmutable::now()],
		);
	}

	public function getSeenAt(): array
	{
		$seenAt = [];

		foreach (CardViewEloquent::query()->where('card', '!=', self::READ_UNTIL_CARD)->get(['card', 'seen_at']) as $cardViewEloquent) {
			$separator = strrpos((string) $cardViewEloquent->card, self::TAB_SEPARATOR);
			$source = $separator === false ? null : UnreadSourceEnum::tryFrom(substr((string) $cardViewEloquent->card, $separator + 1));

			// a row without a tab was written when opening the card read everything at once
			$card = $source === null ? (string) $cardViewEloquent->card : substr((string) $cardViewEloquent->card, 0, (int) $separator);
			$sources = $source === null ? UnreadSourceEnum::cases() : [$source];

			foreach ($sources as $tab) {
				$standing = $seenAt[$card][$tab->value] ?? null;

				if ($standing === null || $cardViewEloquent->seen_at->greaterThan($standing)) {
					$seenAt[$card][$tab->value] = $cardViewEloquent->seen_at;
				}
			}
		}

		return $seenAt;
	}

	public function getReadUntil(): CarbonImmutable
	{
		return CardViewEloquent::query()->where('card', self::READ_UNTIL_CARD)->value('seen_at')
			?? CarbonImmutable::now();
	}
}
