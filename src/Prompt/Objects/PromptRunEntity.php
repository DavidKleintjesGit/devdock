<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Carbon\CarbonImmutable;
use Src\Access\Enums\GitGrantEnum;
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
		public readonly ?GitGrantEnum $gitGrant,
		// the working files as they stood before and after the turn, as trees in git
		public readonly ?string $treeBefore,
		public readonly ?string $treeAfter,
		public readonly PromptStatusEnum $status,
		public readonly ?string $output,
		public readonly ?string $message,
		public readonly ?float $cost,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $finishedAt,
	) {
		parent::__construct();
	}

	/**
	 * Whether the turn kept the working files from before and after it, so its own changes can be read.
	 */
	public function hasSnapshots(): bool
	{
		return $this->treeBefore !== null && $this->treeAfter !== null;
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

	/**
	 * Whether the prompt is long enough to fold away in the chat, as a prompt
	 * built from the ticket usually is.
	 */
	public function hasLongPrompt(): bool
	{
		return mb_strlen($this->prompt) > 600 || substr_count($this->prompt, "\n") > 10;
	}

	public function costLabel(): ?string
	{
		return $this->cost === null ? null : '$' . number_format($this->cost, 2);
	}
}
