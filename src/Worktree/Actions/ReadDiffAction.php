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
 * @method static DiffHunkObjectCollection execute(?string $patch)
 */
class ReadDiffAction extends Command
{
	/**
	 * De regel waarmee git een stuk aankondigt: @@ -oud,aantal +nieuw,aantal @@
	 */
	private const HUNK = '/^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/';

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
		$lines = [];
		$oldNumber = 0;
		$newNumber = 0;

		foreach (preg_split('/\R/', $this->patch) ?: [] as $line) {
			if (preg_match(self::HUNK, $line, $start) === 1) {
				$hunks[] = $this->close($header, $lines);
				$header = $line;
				$lines = [];
				$oldNumber = (int) $start[1];
				$newNumber = (int) $start[2];

				continue;
			}

			// alles voor het eerste stuk is de kop van git zelf
			if ($header === null) {
				continue;
			}

			[$kind, $text] = $this->read($line);

			$lines[] = new DiffLineObject(
				kind: $kind,
				oldNumber: $kind === 'add' ? null : $oldNumber++,
				newNumber: $kind === 'del' ? null : $newNumber++,
				text: $text,
			);
		}

		$hunks[] = $this->close($header, $lines);

		return DiffHunkObjectCollection::fromArray(array_values(array_filter($hunks)));
	}

	/**
	 * @param  array<int, DiffLineObject>  $lines
	 */
	private function close(?string $header, array $lines): ?DiffHunkObject
	{
		return $header === null ? null : new DiffHunkObject(
			header: $header,
			lines: DiffLineObjectCollection::fromArray($lines),
		);
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
