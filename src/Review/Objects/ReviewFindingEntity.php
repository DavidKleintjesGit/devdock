<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\FindingStatusEnum;

/**
 * One point a lens found on a card. It keeps its number from the round it was
 * found in, so a later round can say what became of it.
 */
class ReviewFindingEntity extends ValueObject
{
	public function __construct(
		public readonly ReviewFindingId $reviewFindingId,
		public readonly string $card,
		public readonly LensId $lensId,
		public readonly string $lensName,
		public readonly int $number,
		public readonly ?string $location,
		public readonly string $text,
		public readonly FindingStatusEnum $status,
		public readonly ?string $reason,
		public readonly ReviewRoundId $firstReviewRoundId,
		public readonly int $firstRoundNumber,
		public readonly ReviewRoundId $lastReviewRoundId,
		public readonly ?ReviewRoundId $resolvedReviewRoundId,
		public readonly ?int $resolvedRoundNumber,
	) {
		parent::__construct();
	}

	public function isOpen(): bool
	{
		return $this->status === FindingStatusEnum::OPEN;
	}

	/**
	 * Since when the finding is open, or who set it aside.
	 */
	public function statusNote(): string
	{
		return match (true) {
			$this->status === FindingStatusEnum::OPEN => __('sinds ronde :number', ['number' => $this->firstRoundNumber]),
			$this->resolvedRoundNumber !== null => __('in ronde :number, gezien door de lens', ['number' => $this->resolvedRoundNumber]),
			default => __('door jou'),
		};
	}

	/**
	 * The file the finding points at, without its line, or null when the lens named no file.
	 */
	public function filePath(): ?string
	{
		$path = preg_replace('/:\d+(-\d+)?$/', '', (string) $this->location);

		return $path === '' || str_contains($path, ' ') ? null : $path;
	}

	/**
	 * The finding in one line, as a lens or the agent reads it.
	 */
	public function line(): string
	{
		return '#' . $this->number . ' ' . ($this->location === null ? '' : $this->location . ' — ') . $this->text;
	}
}
