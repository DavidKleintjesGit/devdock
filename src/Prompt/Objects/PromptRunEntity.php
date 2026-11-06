<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Carbon\CarbonImmutable;
use Src\Access\Enums\GitGrantEnum;
use Src\Packages\ValueObject\ValueObject;
use Src\Prompt\Contracts\AttachmentRepositoryContract;
use Src\Prompt\Enums\PromptStatusEnum;

/**
 * A prompt handed to Claude Code from the board.
 */
class PromptRunEntity extends ValueObject
{
	/**
	 * Where the block with the paths of the attachments begins in a message.
	 */
	private const ATTACHMENTS_OPENING = "\n\n```attachments\n";

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
		return mb_strlen($this->message()) > 600 || substr_count($this->message(), "\n") > 10;
	}

	/**
	 * What was written in the message, without the block that lists its attachments.
	 */
	public function message(): string
	{
		return rtrim(explode(self::ATTACHMENTS_OPENING, $this->prompt, 2)[0]);
	}

	/**
	 * The files sent along with the message.
	 *
	 * @return list<AttachmentObject>
	 */
	public function attachments(): array
	{
		$block = explode(self::ATTACHMENTS_OPENING, $this->prompt, 2)[1] ?? null;

		if ($block === null) {
			return [];
		}

		return array_values(array_filter(array_map(
			fn (string $path): ?AttachmentObject => $path === '' ? null : resolve(AttachmentRepositoryContract::class)->fromPath($path),
			array_map('trim', explode("\n", explode('```', $block, 2)[0])),
		)));
	}

	/**
	 * A message with the full paths of its attachments in a block of their own, as the agent reads it.
	 *
	 * @param  list<string>  $paths
	 */
	public static function withAttachments(string $message, array $paths): string
	{
		return $paths === [] ? $message : $message . self::ATTACHMENTS_OPENING . implode("\n", $paths) . "\n```";
	}

	public function costLabel(): ?string
	{
		return $this->cost === null ? null : '$' . number_format($this->cost, 2);
	}
}
