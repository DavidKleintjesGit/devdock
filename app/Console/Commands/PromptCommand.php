<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Prompt\Actions\RunPromptAction;
use Src\Prompt\Objects\PromptRunId;

class PromptCommand extends Command
{
	protected $signature = 'devdock:prompt {--run= : The prompt_runs row to carry out}';

	protected $description = 'Run one prompt through Claude Code and write the answer back to the board';

	public function handle(): int
	{
		$run = $this->option('run');

		if ($run === null) {
			$this->components->error('No prompt run given.');

			return self::FAILURE;
		}

		$promptRunEntity = RunPromptAction::execute(new PromptRunId(id: (int) $run));

		if ($promptRunEntity->hasFailed()) {
			$this->components->error((string) $promptRunEntity->message);

			return self::FAILURE;
		}

		$this->components->info((string) $promptRunEntity->costLabel());

		return self::SUCCESS;
	}
}
