<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * The path a task walks from nothing to merged. A step is where the work
 * stands; the review step is the only one that holds rounds.
 */
enum WorkflowStepEnum: string
{
	case BUILDING = 'building';
	case DELIVERED = 'delivered';
	case REVIEW = 'review';
	case READY_TO_MERGE = 'ready';
	case MERGED = 'merged';

	public function label(): string
	{
		return __(match ($this) {
			self::BUILDING => 'Bouwen',
			self::DELIVERED => 'Opgeleverd',
			self::REVIEW => 'Review',
			self::READY_TO_MERGE => 'Klaar om te mergen',
			self::MERGED => 'Gemerged',
		});
	}

	/**
	 * The name as the context file writes it. That file is one document in
	 * English, so it does not run through the interface translations.
	 */
	public function title(): string
	{
		return match ($this) {
			self::BUILDING => 'Building',
			self::DELIVERED => 'Delivered',
			self::REVIEW => 'Review',
			self::READY_TO_MERGE => 'Ready to merge',
			self::MERGED => 'Merged',
		};
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::BUILDING => 'De agent werkt aan de taak, zo veel berichten als nodig.',
			self::DELIVERED => 'Het werk is af. De ronde geldt voor het commit waarop hij draait.',
			self::REVIEW => 'Alleen de lenzen waarvan de paden geraakt zijn doen mee.',
			self::READY_TO_MERGE => 'Alle lenzen PASS betekent klaar om te mergen.',
			self::MERGED => 'De pull request is gemerged en de werkkopie mag weg.',
		});
	}

	public function number(): int
	{
		return match ($this) {
			self::BUILDING => 1,
			self::DELIVERED => 2,
			self::REVIEW => 3,
			self::READY_TO_MERGE => 4,
			self::MERGED => 5,
		};
	}

	/**
	 * Whether this step comes before the one a task stands on.
	 */
	public function comesBefore(self $step): bool
	{
		return $this->number() < $step->number();
	}
}
