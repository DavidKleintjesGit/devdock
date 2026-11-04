<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Objects\CommitMessageObject;

/**
 * Reads the commit block out of an answer: the first line is the title, the
 * rest the message. Null when the answer holds no block with a title.
 *
 * @method static ?CommitMessageObject execute(?string $text)
 */
class ReadCommitMessageAction extends Command
{
	private const FENCE = 'commit';

	public function __construct(
		public readonly ?string $text,
	) {}

	public function handle(): ?CommitMessageObject
	{
		if (preg_match('/```' . self::FENCE . '\s*\n(.*?)```/s', (string) $this->text, $found) !== 1) {
			return null;
		}

		$lines = explode("\n", trim($found[1]));
		$title = trim((string) array_shift($lines));

		return $title === '' ? null : CommitMessageObject::from([
			'title' => $title,
			'body' => trim(implode("\n", $lines)),
		]);
	}
}
