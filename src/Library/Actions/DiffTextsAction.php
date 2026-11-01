<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Actions\ReadDiffAction;
use Src\Worktree\Objects\DiffHunkObjectCollection;

/**
 * What changed between two texts, in the same hunks the code diff uses.
 * git does the comparing, on two scratch files that are gone afterwards.
 *
 * @method static DiffHunkObjectCollection execute(string $from, string $to)
 */
class DiffTextsAction extends Command
{
	public function __construct(
		public readonly string $from,
		public readonly string $to,
	) {}

	public function handle(): DiffHunkObjectCollection
	{
		$directory = storage_path('app/private/diff/' . Str::random(12));
		mkdir($directory, 0777, true);

		file_put_contents($directory . '/from', str_replace("\r\n", "\n", $this->from));
		file_put_contents($directory . '/to', str_replace("\r\n", "\n", $this->to));

		$result = Process::path($directory)->run(['git', 'diff', '--no-index', '--no-color', '--unified=2', 'from', 'to']);

		@unlink($directory . '/from');
		@unlink($directory . '/to');
		@rmdir($directory);

		return ReadDiffAction::execute(trim($result->output()) === '' ? null : $result->output());
	}
}
