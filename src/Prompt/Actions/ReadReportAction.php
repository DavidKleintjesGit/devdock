<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Enums\ReportStatusEnum;
use Src\Prompt\Objects\CommitMessageObject;
use Src\Prompt\Objects\ReportObject;

/**
 * Lifts the closing report out of what a round wrote. Every prompt asks for
 * the same fenced block, so the card can show the outcome and hang the right
 * buttons off it instead of leaving you to read the whole thing.
 *
 * @method static ?ReportObject execute(?string $text)
 */
class ReadReportAction extends Command
{
	private const FENCE = 'report';

	private const COMMIT_FENCE = 'commit';

	public function __construct(
		public readonly ?string $text,
	) {}

	public function handle(): ?ReportObject
	{
		if ($this->text === null) {
			return null;
		}

		if (preg_match('/```' . self::FENCE . '\s*\n(.*?)```/s', $this->text, $found) !== 1) {
			return null;
		}

		$fields = $this->fields($found[1]);
		$status = ReportStatusEnum::tryFrom(strtolower(trim($fields['status'][0] ?? '')));

		if ($status === null) {
			return null;
		}

		return new ReportObject(
			status: $status,
			summary: trim($fields['summary'][0] ?? ''),
			done: $fields['done'] ?? [],
			open: $fields['open'] ?? [],
			question: trim($fields['question'][0] ?? '') ?: null,
			commitMessage: $this->commitMessage(),
		);
	}

	/**
	 * Reads the block line by line: a `key: value` opens a field, and the
	 * dashed lines under it belong to that same field.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function fields(string $block): array
	{
		$fields = [];
		$key = null;

		foreach (explode("\n", $block) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if (str_starts_with($line, '-')) {
				$value = trim(ltrim($line, '- '));

				if ($key !== null && $value !== '') {
					$fields[$key][] = $value;
				}

				continue;
			}

			if (preg_match('/^([a-z]+)\s*:\s*(.*)$/i', $line, $found) !== 1) {
				continue;
			}

			$key = strtolower($found[1]);
			$fields[$key] ??= [];

			if (trim($found[2]) !== '') {
				$fields[$key][] = trim($found[2]);
			}
		}

		return $fields;
	}

	private function commitMessage(): ?CommitMessageObject
	{
		if (preg_match('/```' . self::COMMIT_FENCE . '\s*\n(.*?)```/s', (string) $this->text, $found) !== 1) {
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
