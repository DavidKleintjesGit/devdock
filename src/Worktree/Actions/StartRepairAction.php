<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Packages\Process\DetachedCommand;
use Src\Worktree\Enums\SetupCheckEnum;

/**
 * Puts a repair of a working copy in a process of its own, one point or all
 * of them. Returns false when a repair of this copy is still running.
 *
 * @method static bool execute(string $directory, ?SetupCheckEnum $setupCheck = null)
 */
class StartRepairAction extends Command
{
	public function __construct(
		public readonly string $directory,
		public readonly ?SetupCheckEnum $setupCheck = null,
	) {}

	public function handle(DetachedCommand $detachedCommand): bool
	{
		if (GetRepairStateAction::execute($this->directory)?->isRunning === true) {
			return false;
		}

		Cache::forever(RepairSetupAction::KEY . md5(CheckReadinessAction::normalize($this->directory)), [
			'is_running' => true,
			'setup_check' => $this->setupCheck?->value,
			'step' => null,
			'started_at' => CarbonImmutable::now()->toIso8601String(),
			'finished_at' => null,
			'failure' => null,
			'log_path' => RepairSetupAction::logPath($this->directory),
		]);

		$detachedCommand->artisan(implode(' ', array_filter([
			'devdock:repair',
			'--directory=' . escapeshellarg($this->directory),
			$this->setupCheck === null ? null : '--check=' . $this->setupCheck->value,
		])));

		return true;
	}
}
