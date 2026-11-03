<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Objects\RepairStateObject;

/**
 * The last repair of a working copy, or null when it never had one.
 *
 * A repair that says it runs but started longer ago than any step may take
 * fell over without writing its end; it counts as stopped.
 *
 * @method static ?RepairStateObject execute(string $directory)
 */
class GetRepairStateAction extends Command
{
	private const STALE_MINUTES = 30;

	public function __construct(
		public readonly string $directory,
	) {}

	public function handle(): ?RepairStateObject
	{
		$kept = Cache::get(RepairSetupAction::KEY . md5(CheckReadinessAction::normalize($this->directory)));

		if (! is_array($kept)) {
			return null;
		}

		$repairStateObject = RepairStateObject::from($kept);

		if ($repairStateObject->isRunning && $repairStateObject->startedAt->addMinutes(self::STALE_MINUTES)->isPast()) {
			return RepairStateObject::from([...$kept, 'is_running' => false, 'failure' => __('De herstelactie stopte zonder af te ronden. Kijk in het log.')]);
		}

		return $repairStateObject;
	}
}
