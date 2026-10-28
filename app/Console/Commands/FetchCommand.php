<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Fetch\Actions\OpenFetchRunAction;
use Src\Fetch\Actions\RunFetchAction;
use Src\Fetch\Enums\FetchSourceEnum;
use Src\Fetch\Objects\FetchRunId;

class FetchCommand extends Command
{
	protected $signature = 'devdock:fetch
		{--only= : One source only: projects, sessions, rules, skills, tickets or pull-requests}
		{--run= : The fetch_runs row to report progress into}';

	protected $description = 'Read the sources into the board: Jira, Bitbucket, the disk and the Claude sessions';

	public function handle(): int
	{
		$only = $this->option('only');
		$source = $only === null ? null : FetchSourceEnum::tryFrom((string) $only);

		if ($only !== null && $source === null) {
			$this->components->error('No such source: ' . $only);

			return self::FAILURE;
		}

		$fetchRunId = $this->fetchRunId();
		$fetchRunEntity = RunFetchAction::execute($fetchRunId, $source);

		$this->components->info($fetchRunEntity->resultLine());

		if ($fetchRunEntity->message !== null) {
			$this->components->warn($fetchRunEntity->message);
		}

		return self::SUCCESS;
	}

	/**
	 * The row the progress is kept in. When the command runs on its own, it
	 * opens one itself.
	 */
	private function fetchRunId(): FetchRunId
	{
		$run = $this->option('run');

		return $run === null
			? OpenFetchRunAction::execute()->fetchRunId
			: new FetchRunId(id: (int) $run);
	}
}
