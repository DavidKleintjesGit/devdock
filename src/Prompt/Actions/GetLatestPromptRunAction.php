<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Packages\Process\LocalCommand;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;

/**
 * @method static ?PromptRunEntity execute(string $card)
 */
class GetLatestPromptRunAction extends Command
{
	/**
	 * How long the log of a running turn may stay quiet before DevDock checks
	 * that its process still lives; a long test run writes nothing for minutes.
	 */
	private const QUIET_SECONDS = 120;

	public function __construct(
		public readonly string $card,
	) {
	}

	public function handle(PromptRunRepositoryContract $promptRunRepository): ?PromptRunEntity
	{
		$promptRunEntity = $promptRunRepository->getLatestForCard($this->card);

		if ($promptRunEntity === null || ! $promptRunEntity->isRunning()) {
			return $promptRunEntity;
		}

		$logPath = $promptRunRepository->logPath($promptRunEntity->promptRunId);
		$processFile = self::processFile($logPath);
		$lastSign = max(@filemtime($logPath) ?: 0, @filemtime($processFile) ?: 0);

		if (! is_file($processFile) || time() - $lastSign < self::QUIET_SECONDS || $this->isAlive((int) file_get_contents($processFile))) {
			return $promptRunEntity;
		}

		return $promptRunRepository->markFailed($promptRunEntity->promptRunId, __('Het proces van deze beurt stopte zonder af te ronden. Stuur je bericht opnieuw.'));
	}

	/**
	 * The file next to the log of a turn that holds the number of its process.
	 */
	public static function processFile(string $logPath): string
	{
		return (string) preg_replace('/\.jsonl$/', '', $logPath) . '.pid';
	}

	private function isAlive(int $processId): bool
	{
		if ($processId <= 0) {
			return false;
		}

		if (PHP_OS_FAMILY !== 'Windows') {
			return file_exists('/proc/' . $processId) || (function_exists('posix_kill') && posix_kill($processId, 0));
		}

		$tasks = LocalCommand::run(base_path(), ['tasklist', '/FI', 'PID eq ' . $processId, '/NH', '/FO', 'CSV']);

		// tasklist answers that no task matches in the language of Windows, so only a line with the number counts
		return str_contains($tasks->output(), '"' . $processId . '"');
	}
}
