<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Src\Worktree\Actions\ReadDiffAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DiffViewEnum;

/**
 * The changes of one file in the code reader. It loads once its card comes
 * into view, and opening it up further redraws only this file.
 */
#[Lazy]
class DiffFile extends Component
{
	/**
	 * How many lines one click on an expander shows more.
	 */
	private const EXPAND_LINES = 20;

	/**
	 * Enough lines around a change to show any file whole.
	 */
	private const WHOLE_FILE_LINES = 100000;

	/**
	 * A patch beyond this many bytes waits for a click, the way a pull request
	 * leaves a large diff unrendered.
	 */
	private const MAX_PATCH_BYTES = 300_000;

	/**
	 * A line this long is minified or generated; drawing it stalls the page.
	 */
	private const MAX_LINE_CHARACTERS = 2_000;

	/**
	 * The height a placeholder line takes, so the page does not jump while
	 * the files come in.
	 */
	private const LINE_PIXELS = 20;

	#[Locked]
	public string $worktreePath;

	#[Locked]
	public string $file;

	#[Locked]
	public string $view;

	/** The changed lines, to size the placeholder before the file is read. */
	#[Locked]
	public int $lines = 10;

	public int $context = 3;

	public bool $isForced = false;

	public function expand(bool $whole = false): void
	{
		$this->context = $whole ? self::WHOLE_FILE_LINES : $this->context + self::EXPAND_LINES;
	}

	public function loadAnyway(): void
	{
		$this->isForced = true;
	}

	/**
	 * @param  array<string, mixed>  $params
	 */
	public function placeholder(array $params = []): string
	{
		$height = min(600, max(3, (int) ($params['lines'] ?? 10) + 6)) * self::LINE_PIXELS;

		return '<div class="diff diff-loading" style="height:' . $height . 'px"></div>';
	}

	public function render(): View
	{
		$patch = resolve(WorktreeRepositoryContract::class)->getDiff($this->worktreePath, $this->file, DiffViewEnum::from($this->view), $this->context);
		$longestLine = $patch === null ? 0 : max(array_map('strlen', preg_split('/\R/', $patch) ?: ['']));
		$isTooLarge = ! $this->isForced && $patch !== null && (strlen($patch) > self::MAX_PATCH_BYTES || $longestLine > self::MAX_LINE_CHARACTERS);

		return view('livewire.diff-file', [
			'isTooLarge' => $isTooLarge,
			'hunks' => ReadDiffAction::execute($isTooLarge ? null : $patch),
		]);
	}
}
