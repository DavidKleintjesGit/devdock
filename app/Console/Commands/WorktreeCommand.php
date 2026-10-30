<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

class WorktreeCommand extends Command
{
	protected $signature = 'devdock:worktree
		{project : The folder name of the project}
		{branch : The branch this task works on}
		{--card= : The board card the working copy belongs to}';

	protected $description = 'Lay down the working copy for a branch, with its packages, its settings and its own address';

	public function handle(): int
	{
		try {
			$worktreeEntity = EnsureWorktreeAction::execute(
				(string) $this->argument('project'),
				(string) $this->argument('branch'),
				$this->option('card'),
			);
		} catch (WorktreeNotPossibleException $exception) {
			$this->components->error($exception->getMessage());

			return self::FAILURE;
		}

		$this->components->info($worktreeEntity->path);

		if ($worktreeEntity->url() !== null) {
			$this->components->twoColumnDetail('Opens at', $worktreeEntity->url());
		}

		if ($worktreeEntity->dependenciesMissing) {
			$this->components->warn('No vendor here: the lock file differs from the checkout, so run composer install in this folder.');
		}

		return self::SUCCESS;
	}
}
