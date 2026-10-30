<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * What a first prompt can be built from. Which of these a task offers depends
 * on what hangs on it.
 */
enum PromptSourceEnum: string
{
	case TICKET = 'ticket';
	case NOTE = 'note';
	case REVIEW = 'review';
	case ROUND = 'round';

	public function label(): string
	{
		return __(match ($this) {
			self::TICKET => 'Uit het ticket',
			self::NOTE => 'Uit de notitie',
			self::REVIEW => 'Uit de review',
			self::ROUND => 'Uit de ronde',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::TICKET => 'blue',
			self::NOTE => 'amber',
			self::REVIEW => 'red',
			self::ROUND => 'red',
		};
	}
}
