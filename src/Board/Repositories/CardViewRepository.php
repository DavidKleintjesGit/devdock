<?php

declare(strict_types=1);

namespace Src\Board\Repositories;

use Carbon\CarbonImmutable;
use Src\Board\Contracts\CardViewRepositoryContract;
use Src\Board\Models\CardView as CardViewEloquent;

class CardViewRepository implements CardViewRepositoryContract
{
	/**
	 * The row that holds when the badges arrived, set by its migration.
	 */
	private const READ_UNTIL_CARD = '*';

	public function markSeen(string $card): void
	{
		CardViewEloquent::query()->updateOrCreate(['card' => $card], ['seen_at' => CarbonImmutable::now()]);
	}

	public function getSeenAt(): array
	{
		return CardViewEloquent::query()
			->where('card', '!=', self::READ_UNTIL_CARD)
			->pluck('seen_at', 'card')
			->all();
	}

	public function getReadUntil(): CarbonImmutable
	{
		return CardViewEloquent::query()->where('card', self::READ_UNTIL_CARD)->value('seen_at')
			?? CarbonImmutable::now();
	}
}
