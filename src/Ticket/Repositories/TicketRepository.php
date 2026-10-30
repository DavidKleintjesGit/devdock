<?php

declare(strict_types=1);

namespace Src\Ticket\Repositories;

use Src\Ticket\Contracts\TicketRepositoryContract;
use Src\Ticket\Models\Ticket as TicketEloquent;
use Src\Ticket\Objects\TicketEntity;
use Src\Ticket\Objects\TicketEntityCollection;
use Src\Ticket\Objects\TicketId;
use Src\Ticket\Objects\TicketObject;
use Src\Ticket\Objects\TicketObjectCollection;

class TicketRepository implements TicketRepositoryContract
{
	public function create(TicketObject $ticketObject): TicketEntity
	{
		$ticketEloquent = TicketEloquent::query()->updateOrCreate(
			['key' => $ticketObject->key],
			[
				'summary' => $ticketObject->summary,
				'description' => $ticketObject->description,
				'status' => $ticketObject->status,
				'type' => $ticketObject->type,
				'priority' => $ticketObject->priority,
				'url' => $ticketObject->url,
				'changed_at' => $ticketObject->changedAt,
			],
		);

		return $this->mapToEntity($ticketEloquent);
	}

	public function getAll(): TicketEntityCollection
	{
		$ticketEntities = TicketEloquent::query()
			->orderByDesc('changed_at')
			->get()
			->map(fn (TicketEloquent $ticketEloquent) => $this->mapToEntity($ticketEloquent))
			->all();

		return TicketEntityCollection::fromArray($ticketEntities);
	}

	public function getByKey(string $key): ?TicketEntity
	{
		$ticketEloquent = TicketEloquent::query()->where('key', $key)->first();

		return $ticketEloquent === null ? null : $this->mapToEntity($ticketEloquent);
	}

	public function replaceAll(TicketObjectCollection $ticketObjects): TicketEntityCollection
	{
		$keys = [];

		foreach ($ticketObjects as $ticketObject) {
			$this->create($ticketObject);
			$keys[] = $ticketObject->key;
		}

		if ($keys !== []) {
			TicketEloquent::query()->whereNotIn('key', $keys)->delete();
		}

		return $this->getAll();
	}

	private function mapToEntity(TicketEloquent $ticketEloquent): TicketEntity
	{
		return new TicketEntity(
			ticketId: new TicketId(id: $ticketEloquent->id),
			key: (string) $ticketEloquent->key,
			summary: (string) $ticketEloquent->summary,
			description: $ticketEloquent->description,
			status: $ticketEloquent->status,
			type: $ticketEloquent->type,
			priority: $ticketEloquent->priority,
			url: $ticketEloquent->url,
			changedAt: $ticketEloquent->changed_at,
		);
	}
}
