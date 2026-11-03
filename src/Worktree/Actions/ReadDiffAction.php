<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Objects\DiffHunkObject;
use Src\Worktree\Objects\DiffHunkObjectCollection;
use Src\Worktree\Objects\DiffLineObject;
use Src\Worktree\Objects\DiffLineObjectCollection;

/**
 * Leest de uitvoer van git diff en maakt er stukken met genummerde regels
 * van. Die nummering staat niet in de uitvoer: git noemt alleen per stuk waar
 * het begint, en vanaf daar telt een toegevoegde regel alleen in de nieuwe
 * kolom mee en een verwijderde alleen in de oude.
 *
 * A removed line followed by an added one is read as one line that changed:
 * both get the part that differs marked, the way a pull request shows it.
 *
 * @method static DiffHunkObjectCollection execute(?string $patch)
 */
class ReadDiffAction extends Command
{
	/**
	 * De regel waarmee git een stuk aankondigt: @@ -oud,aantal +nieuw,aantal @@
	 */
	private const HUNK = '/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,\d+)? @@/';

	public function __construct(
		public readonly ?string $patch,
	) {}

	public function handle(): DiffHunkObjectCollection
	{
		if (blank($this->patch)) {
			return DiffHunkObjectCollection::fromArray([]);
		}

		$hunks = [];
		$header = null;
		$rows = [];
		$oldStart = 0;
		$oldCount = 0;
		$oldNumber = 0;
		$newNumber = 0;

		foreach (preg_split('/\R/', $this->patch) ?: [] as $line) {
			if (preg_match(self::HUNK, $line, $start) === 1) {
				$hunks[] = $this->close($header, $rows, $oldStart, $oldCount);
				$header = $line;
				$rows = [];
				$oldStart = (int) $start[1];
				$oldCount = ($start[2] ?? '') === '' ? 1 : (int) $start[2];
				$oldNumber = (int) $start[1];
				$newNumber = (int) $start[3];

				continue;
			}

			// alles voor het eerste stuk is de kop van git zelf, en "\ No newline" hoort bij de regel erboven
			if ($header === null || str_starts_with($line, '\\')) {
				continue;
			}

			[$kind, $text] = $this->read($line);

			$rows[] = [
				'kind' => $kind,
				'old' => $kind === 'add' ? null : $oldNumber++,
				'new' => $kind === 'del' ? null : $newNumber++,
				'text' => $text,
				'mark' => null,
			];
		}

		$hunks[] = $this->close($header, $rows, $oldStart, $oldCount);

		return DiffHunkObjectCollection::fromArray($this->withHiddenLines(array_values(array_filter($hunks))));
	}

	/**
	 * Counts for every piece how many lines of the file lie hidden before it.
	 *
	 * @param  array<int, DiffHunkObject>  $hunks
	 * @return array<int, DiffHunkObject>
	 */
	private function withHiddenLines(array $hunks): array
	{
		$previousEnd = 1;

		foreach ($hunks as $index => $hunk) {
			$hunks[$index] = new DiffHunkObject(
				header: $hunk->header,
				lines: $hunk->lines,
				oldStart: $hunk->oldStart,
				oldCount: $hunk->oldCount,
				hiddenBefore: max(0, $hunk->oldStart - $previousEnd),
			);

			$previousEnd = $hunk->oldEnd();
		}

		return $hunks;
	}

	/**
	 * @param  array<int, array<string, mixed>>  $rows
	 */
	private function close(?string $header, array $rows, int $oldStart, int $oldCount): ?DiffHunkObject
	{
		if ($header === null) {
			return null;
		}

		$rows = $this->markChangedParts($rows);

		return new DiffHunkObject(
			header: $header,
			lines: DiffLineObjectCollection::fromArray(array_map(fn (array $row) => new DiffLineObject(
				kind: $row['kind'],
				oldNumber: $row['old'],
				newNumber: $row['new'],
				text: $row['text'],
				markStart: $row['mark'][0] ?? null,
				markEnd: $row['mark'][1] ?? null,
			), $rows)),
			oldStart: $oldStart,
			oldCount: $oldCount,
		);
	}

	/**
	 * Pairs each run of removed lines with the run of added lines right after
	 * it, line by line, and marks what lies between the part they share at the
	 * start and the part they share at the end.
	 *
	 * @param  array<int, array<string, mixed>>  $rows
	 * @return array<int, array<string, mixed>>
	 */
	private function markChangedParts(array $rows): array
	{
		$count = count($rows);
		$index = 0;

		while ($index < $count) {
			if ($rows[$index]['kind'] !== 'del') {
				$index++;

				continue;
			}

			$removed = [];

			while ($index < $count && $rows[$index]['kind'] === 'del') {
				$removed[] = $index++;
			}

			$added = [];

			while ($index < $count && $rows[$index]['kind'] === 'add') {
				$added[] = $index++;
			}

			foreach (array_map(null, $removed, $added) as [$removedIndex, $addedIndex]) {
				if ($removedIndex === null || $addedIndex === null) {
					break;
				}

				[$removedMark, $addedMark] = $this->changedParts($rows[$removedIndex]['text'], $rows[$addedIndex]['text']);

				$rows[$removedIndex]['mark'] = $removedMark;
				$rows[$addedIndex]['mark'] = $addedMark;
			}
		}

		return $rows;
	}

	/**
	 * The differing middle of two versions of a line, as start and end in
	 * characters; nothing when the lines share nothing, since then the whole
	 * line changed.
	 *
	 * @return array{0: ?array{0: int, 1: int}, 1: ?array{0: int, 1: int}}
	 */
	private function changedParts(string $before, string $after): array
	{
		$beforeCharacters = mb_str_split($before);
		$afterCharacters = mb_str_split($after);
		$beforeLength = count($beforeCharacters);
		$afterLength = count($afterCharacters);

		$prefix = 0;

		while ($prefix < $beforeLength && $prefix < $afterLength && $beforeCharacters[$prefix] === $afterCharacters[$prefix]) {
			$prefix++;
		}

		$suffix = 0;

		while (
			$suffix < $beforeLength - $prefix
			&& $suffix < $afterLength - $prefix
			&& $beforeCharacters[$beforeLength - 1 - $suffix] === $afterCharacters[$afterLength - 1 - $suffix]
		) {
			$suffix++;
		}

		// widen the mark to whole words, so a name that changed is marked as a whole
		while ($prefix > 0 && $this->isWordCharacter($beforeCharacters[$prefix - 1])) {
			$prefix--;
		}

		while ($suffix > 0 && $this->isWordCharacter($beforeCharacters[$beforeLength - $suffix])) {
			$suffix--;
		}

		// only indentation in common is nothing in common
		$shared = trim(implode('', array_slice($beforeCharacters, 0, $prefix))) . implode('', array_slice($beforeCharacters, $beforeLength - $suffix));

		if (trim($shared) === '') {
			return [null, null];
		}

		return [
			$prefix < $beforeLength - $suffix ? [$prefix, $beforeLength - $suffix] : null,
			$prefix < $afterLength - $suffix ? [$prefix, $afterLength - $suffix] : null,
		];
	}

	private function isWordCharacter(string $character): bool
	{
		return preg_match('/^[\p{L}\p{N}_]$/u', $character) === 1;
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private function read(string $line): array
	{
		return match (substr($line, 0, 1)) {
			'+' => ['add', substr($line, 1)],
			'-' => ['del', substr($line, 1)],
			default => ['keep', substr($line, 1)],
		};
	}
}
