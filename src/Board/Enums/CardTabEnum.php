<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * A tab of the card panel. The value is what the url and the unread badges use.
 */
enum CardTabEnum: string
{
	case TICKET = 'Overzicht';
	case CHAT = 'Agent';
	case WORKING_COPY = 'Werkkopie';
	case REVIEW = 'Review';
	case LINKS = 'Koppelingen';

	public function label(): string
	{
		return __(match ($this) {
			self::TICKET => 'Ticket',
			self::CHAT => 'Chat',
			self::WORKING_COPY => 'Werkkopie',
			self::REVIEW => 'Review',
			self::LINKS => 'Koppelingen',
		});
	}

	/**
	 * The name of the icon in the sprite.
	 */
	public function icon(): string
	{
		return match ($this) {
			self::TICKET => 'ticket',
			self::CHAT => 'chat',
			self::WORKING_COPY => 'folder',
			self::REVIEW => 'eye',
			self::LINKS => 'link',
		};
	}

	/**
	 * The subtabs of this tab, the first one opening by default.
	 *
	 * @return list<CardSubtabEnum>
	 */
	public function subtabs(): array
	{
		return match ($this) {
			self::CHAT => [CardSubtabEnum::CONVERSATION, CardSubtabEnum::CONTEXT],
			self::WORKING_COPY => [CardSubtabEnum::CHANGES, CardSubtabEnum::BRANCH, CardSubtabEnum::SETUP, CardSubtabEnum::COPY],
			self::REVIEW => [CardSubtabEnum::ROUNDS, CardSubtabEnum::LENSES],
			default => [],
		};
	}

	/**
	 * The subtab that is open: the one asked for when this tab has it, else the first.
	 */
	public function subtab(?string $value): ?CardSubtabEnum
	{
		$subtab = CardSubtabEnum::tryFrom((string) $value);

		return in_array($subtab, $this->subtabs(), true) ? $subtab : ($this->subtabs()[0] ?? null);
	}
}
