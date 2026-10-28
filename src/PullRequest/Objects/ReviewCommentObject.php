<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A review comment as Bitbucket returned it.
 */
class ReviewCommentObject extends ValueObject
{
	/**
	 * The opening line a reviewer uses to hand a ready-made prompt to Claude.
	 */
	private const PROMPT_OPENING = 'Geef de volgende prompt aan Claude';

	public function __construct(
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

	public static function from(array $data): self
	{
		$body = (string) ($data['content']['raw'] ?? '');

		return new self(
			commentId: (int) $data['id'],
			parentCommentId: isset($data['parent']['id']) ? (int) $data['parent']['id'] : null,
			author: $data['user']['display_name'] ?? null,
			body: $body,
			path: $data['inline']['path'] ?? null,
			line: isset($data['inline']['to']) ? (int) $data['inline']['to'] : null,
			isPrompt: self::readsAsPrompt($body, isset($data['inline'])),
			url: $data['links']['html']['href'] ?? null,
			commentedAt: isset($data['created_on']) ? CarbonImmutable::parse($data['created_on']) : null,
		);
	}

	/**
	 * Whether this comment carries a prompt the reviewer wrote out for Claude
	 * instead of a finding of its own. It hangs on the pull request as a whole
	 * and holds the prompt in a fenced block.
	 */
	private static function readsAsPrompt(string $body, bool $isInline): bool
	{
		return ! $isInline
			&& str_contains($body, self::PROMPT_OPENING)
			&& str_contains($body, '```');
	}
}
