<?php

declare(strict_types=1);

namespace Src\Account\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What the login page shows before anyone types: whether there is an account
 * to log in to, how long the wait after too many attempts still lasts, and
 * the card you go back to once you are in.
 */
final class LoginStateObject extends ValueObject
{
	public function __construct(
		public readonly bool $hasAccount,
		public readonly int $secondsLocked,
		public readonly ?string $resumeCard,
		public readonly ?string $resumeTab,
	) {
		parent::__construct();
	}

	public function isLockedOut(): bool
	{
		return $this->secondsLocked > 0;
	}

	/**
	 * The wait that is left, as minutes and seconds: 0:42.
	 */
	public function lockedFor(): string
	{
		return sprintf('%d:%02d', intdiv($this->secondsLocked, 60), $this->secondsLocked % 60);
	}

	/**
	 * The sentence that tells you where you continue after logging in.
	 */
	public function resumeLine(): ?string
	{
		if ($this->resumeCard === null) {
			return null;
		}

		// a card without a ticket key is known by an inner key such as todo:12, which says nothing on screen
		if (str_contains($this->resumeCard, ':')) {
			return __('Daarna ga je verder op de kaart die open stond.');
		}

		return $this->resumeTab === null
			? __('Daarna ga je verder waar je was: :card.', ['card' => $this->resumeCard])
			: __('Daarna ga je verder waar je was: :card, tab :tab.', ['card' => $this->resumeCard, 'tab' => $this->resumeTab]);
	}
}
