<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * An open remark a reviewer left on a pull request.
 */
class ReviewCommentEntity extends ValueObject
{
	/**
	 * The line the review tool signs its remarks off with.
	 */
	private const SIGNATURE = '_AI-review';

	public function __construct(
		public readonly ReviewCommentId $reviewCommentId,
		public readonly PullRequestId $pullRequestId,
		public readonly int $commentId,
		public readonly ?int $parentCommentId,
		public readonly ?string $author,
		public readonly string $body,
		public readonly ?string $path,
		public readonly ?int $line,
		public readonly bool $isPrompt,
		public readonly ?string $url,
		public readonly ?CarbonImmutable $commentedAt,
	) {
		parent::__construct();
	}

	/**
	 * The heading the reviewer gave the remark, or its first line when there
	 * is none.
	 */
	public function title(): string
	{
		foreach ($this->lines() as $line) {
			if (preg_match('/^\*\*(.+?)\*\*$/', $line, $found) === 1) {
				return $found[1];
			}

			return str($line)->limit(80)->value();
		}

		return __('Zonder tekst');
	}

	/**
	 * How heavy the reviewer called it, such as 'Middel · Bug'.
	 */
	public function weight(): ?string
	{
		foreach ($this->lines() as $line) {
			if (preg_match('/^_(.+?)_$/', $line, $found) === 1 && ! str_starts_with($line, self::SIGNATURE)) {
				return $found[1];
			}
		}

		return null;
	}

	/**
	 * Whether this remark answers another one instead of raising something
	 * of its own.
	 */
	public function isReply(): bool
	{
		return $this->parentCommentId !== null;
	}

	/**
	 * Where in the code the remark hangs.
	 */
	public function location(): ?string
	{
		if ($this->path === null) {
			return null;
		}

		return $this->line === null ? $this->path : $this->path . ':' . $this->line;
	}

	/**
	 * The remark without the line the review tool signs off with; that line
	 * says nothing about the code.
	 */
	public function withoutSignature(): string
	{
		$kept = array_filter(
			explode("\n", $this->body),
			fn (string $line) => ! str_starts_with(trim($line), self::SIGNATURE),
		);

		return trim(implode("\n", $kept));
	}

	/**
	 * @return list<string>
	 */
	private function lines(): array
	{
		return array_values(array_filter(
			array_map(fn (string $line) => trim($line), explode("\n", $this->body)),
			fn (string $line) => $line !== '',
		));
	}
}
