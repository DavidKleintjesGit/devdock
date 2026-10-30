<?php

declare(strict_types=1);

namespace Src\Prompt\Enums;

/**
 * How a round ended, in the session's own words. It decides what the card
 * offers next: finishing the work, answering, or stepping in yourself.
 */
enum ReportStatusEnum: string
{
	case DONE = 'done';
	case QUESTION = 'question';
	case BLOCKED = 'blocked';

	public function label(): string
	{
		return __(match ($this) {
			self::DONE => 'Klaar',
			self::QUESTION => 'Vraag',
			self::BLOCKED => 'Vastgelopen',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::DONE => 'green',
			self::QUESTION => 'amber',
			self::BLOCKED => 'red',
		};
	}

	/**
	 * What the card asks of you once a round ends this way.
	 */
	public function waitsOn(): string
	{
		return __(match ($this) {
			self::DONE => 'Nakijken en committen',
			self::QUESTION => 'Antwoord geven',
			self::BLOCKED => 'Zelf oppakken',
		});
	}
}
