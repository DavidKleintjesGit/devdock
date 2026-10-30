<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Prompt\Enums\PromptStatusEnum;

/**
 * A prompt handed to Claude Code from the board.
 */
class PromptRunEntity extends ValueObject
{
	public function __construct(
		public readonly PromptRunId $promptRunId,
		public readonly string $card,
		public readonly ?int $reviewRoundId,
		public readonly ?string $project,
		public readonly ?string $branch,
		public readonly ?string $directory,
		public readonly ?string $ticketKey,
		public readonly ?string $sessionUuid,
		public readonly bool $claimsSession,
		public readonly string $prompt,
		public readonly PromptStatusEnum $status,
		public readonly ?string $output,
		public readonly ?string $message,
		public readonly ?float $cost,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $finishedAt,
	) {
		parent::__construct();
	}

	public function isRunning(): bool
	{
		return $this->status === PromptStatusEnum::RUNNING;
	}

	public function hasFailed(): bool
	{
		return $this->status === PromptStatusEnum::FAILED;
	}

	/**
	 * How long Claude Code has taken so far.
	 */
	public function seconds(): ?int
	{
		if ($this->startedAt === null) {
			return null;
		}

		$until = $this->finishedAt ?? CarbonImmutable::now();

		return (int) $until->diffInSeconds($this->startedAt, absolute: true);
	}

	public function costLabel(): ?string
	{
		return $this->cost === null ? null : '$' . number_format($this->cost, 2);
	}
}
