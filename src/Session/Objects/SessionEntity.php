<?php

declare(strict_types=1);

namespace Src\Session\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Session\Enums\SessionGroupEnum;
use Src\Session\Enums\SessionStatusEnum;

/**
 * A conversation with Claude Code, as it stands in the sessions folder.
 */
class SessionEntity extends ValueObject
{
	public function __construct(
		public readonly SessionId $sessionId,
		public readonly string $uuid,
		public readonly string $folder,
		public readonly ?string $project,
		public readonly ?string $title,
		public readonly ?string $branch,
		public readonly int $promptCount,
		public readonly int $bytes,
		public readonly ?CarbonImmutable $lastActiveAt,
		public readonly string $fingerprint,
		public readonly SessionStatusEnum $status,
		public readonly SessionGroupEnum $group,
		public readonly bool $isLarge,
	) {
		parent::__construct();
	}

	/**
	 * The name of the session, or that none was given.
	 */
	public function name(): string
	{
		return $this->title ?? __('Zonder naam');
	}

	/**
	 * The ticket the branch of this session points at.
	 */
	public function ticketKey(): ?string
	{
		preg_match('/CSM-\d{3,5}/', (string) $this->branch, $found);

		return $found[0] ?? null;
	}

	/**
	 * The ticket from the branch when the session name does not follow that
	 * ticket.
	 */
	public function mismatchedTicket(): ?string
	{
		$ticketKey = $this->ticketKey();

		if ($ticketKey === null || $this->title === null) {
			return null;
		}

		return str_contains($this->title, $ticketKey) ? null : $ticketKey;
	}

	/**
	 * The name that puts the ticket from the branch up front.
	 */
	public function proposedTitle(): ?string
	{
		$ticketKey = $this->mismatchedTicket();

		return $ticketKey === null ? null : $ticketKey . ' · ' . $this->name();
	}

	public function megabytes(): float
	{
		return round($this->bytes / 1048576, 1);
	}

	public function sizeLabel(): string
	{
		$decimal = app()->getLocale() === 'nl' ? ',' : '.';

		return number_format($this->megabytes(), 1, $decimal, '') . ' MB';
	}

	public function lastActiveLabel(): string
	{
		return $this->lastActiveAt?->diffForHumans() ?? __('onbekend');
	}

	/**
	 * Where the session file stands, counted from the sessions folder.
	 */
	public function relativePath(): string
	{
		return $this->folder . '/' . $this->uuid . '.jsonl';
	}

	public function matches(string $term): bool
	{
		$haystack = strtolower(implode(' ', [$this->title, $this->branch, $this->uuid, $this->project]));

		return str_contains($haystack, strtolower($term));
	}
}
