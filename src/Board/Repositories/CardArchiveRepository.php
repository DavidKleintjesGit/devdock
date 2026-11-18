<?php

declare(strict_types=1);

namespace Src\Board\Repositories;

use Carbon\CarbonImmutable;
use Src\Board\Contracts\CardArchiveRepositoryContract;
use Src\Board\Models\CardArchive as CardArchiveEloquent;
use Src\Board\Objects\CardArchiveEntity;
use Src\Board\Objects\CardArchiveEntityCollection;

class CardArchiveRepository implements CardArchiveRepositoryContract
{
	public function getAll(): CardArchiveEntityCollection
	{
		return CardArchiveEntityCollection::fromArray(CardArchiveEloquent::query()
			->get()
			->map(fn (CardArchiveEloquent $cardArchiveEloquent): CardArchiveEntity => $this->mapToEntity($cardArchiveEloquent))
			->all());
	}

	public function getFor(string $card): ?CardArchiveEntity
	{
		$cardArchiveEloquent = CardArchiveEloquent::query()->where('card', $card)->first();

		return $cardArchiveEloquent === null ? null : $this->mapToEntity($cardArchiveEloquent);
	}

	public function markCleanedUp(string $card, ?string $project, ?string $branch, ?string $agentSessionUuid): CardArchiveEntity
	{
		$cardArchiveEloquent = CardArchiveEloquent::query()->firstOrNew(['card' => $card]);

		// a second clean-up finds nothing left, so what the first one noted stays
		$cardArchiveEloquent->fill([
			'project' => $project ?? $cardArchiveEloquent->project,
			'branch' => $branch ?? $cardArchiveEloquent->branch,
			'agent_session_uuid' => $agentSessionUuid ?? $cardArchiveEloquent->agent_session_uuid,
			'cleaned_up_at' => CarbonImmutable::now(),
		])->save();

		return $this->mapToEntity($cardArchiveEloquent);
	}

	public function archive(string $card, string $resurfaceSignature): CardArchiveEntity
	{
		$cardArchiveEloquent = CardArchiveEloquent::query()->firstOrNew(['card' => $card]);

		$cardArchiveEloquent->fill([
			'archived_at' => CarbonImmutable::now(),
			'resurface_signature' => $resurfaceSignature,
		])->save();

		return $this->mapToEntity($cardArchiveEloquent);
	}

	public function forget(string $card): void
	{
		CardArchiveEloquent::query()->where('card', $card)->delete();
	}

	private function mapToEntity(CardArchiveEloquent $cardArchiveEloquent): CardArchiveEntity
	{
		return new CardArchiveEntity(
			card: (string) $cardArchiveEloquent->card,
			project: $cardArchiveEloquent->project,
			branch: $cardArchiveEloquent->branch,
			agentSessionUuid: $cardArchiveEloquent->agent_session_uuid,
			cleanedUpAt: $cardArchiveEloquent->cleaned_up_at,
			archivedAt: $cardArchiveEloquent->archived_at,
			resurfaceSignature: $cardArchiveEloquent->resurface_signature,
		);
	}
}
