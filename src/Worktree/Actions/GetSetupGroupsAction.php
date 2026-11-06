<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Enums\CheckStateEnum;
use Src\Worktree\Enums\SetupCheckEnum;
use Src\Worktree\Enums\SetupGroupEnum;
use Src\Worktree\Enums\SetupPointStateEnum;
use Src\Worktree\Objects\ReadinessObject;
use Src\Worktree\Objects\RepairStateObject;
use Src\Worktree\Objects\SetupCheckObject;
use Src\Worktree\Objects\SetupPointObject;

/**
 * The checklist of a working copy in its groups, every point with where it
 * stands in the running or the last repair.
 *
 * @method static array execute(ReadinessObject $readinessObject, ?RepairStateObject $repairStateObject)
 */
class GetSetupGroupsAction extends Command
{
	public function __construct(
		public readonly ReadinessObject $readinessObject,
		public readonly ?RepairStateObject $repairStateObject,
	) {}

	/**
	 * @return list<array{group: SetupGroupEnum, points: list<SetupPointObject>, state: SetupPointStateEnum}>
	 */
	public function handle(): array
	{
		$setupChecks = collect($this->readinessObject->setupChecks)
			->keyBy(fn (SetupCheckObject $setupCheckObject) => $setupCheckObject->setupCheck->value);

		return collect(SetupGroupEnum::cases())
			->map(function (SetupGroupEnum $setupGroup) use ($setupChecks): array {
				$points = $setupChecks
					->filter(fn (SetupCheckObject $setupCheckObject) => SetupGroupEnum::of($setupCheckObject->setupCheck) === $setupGroup)
					->map(fn (SetupCheckObject $setupCheckObject) => $this->point($setupCheckObject, $setupChecks->all()))
					->values()
					->all();

				return [
					'group' => $setupGroup,
					'points' => $points,
					'state' => collect($points)->map(fn (SetupPointObject $setupPointObject) => $setupPointObject->state)
						->sortByDesc(fn (SetupPointStateEnum $setupPointState) => $setupPointState->weight())
						->first() ?? SetupPointStateEnum::DONE,
				];
			})
			->filter(fn (array $group) => $group['points'] !== [])
			->values()
			->all();
	}

	/**
	 * @param  array<string, SetupCheckObject>  $setupChecks
	 */
	private function point(SetupCheckObject $setupCheckObject, array $setupChecks): SetupPointObject
	{
		$setupCheck = $setupCheckObject->setupCheck;
		$isRunning = $this->repairStateObject?->isRunning ?? false;
		$failure = $this->repairStateObject?->failureOf($setupCheck);
		$blockers = collect($setupCheck->waitsFor())
			->filter(fn (SetupCheckEnum $waitsFor) => isset($setupChecks[$waitsFor->value]) && ! $setupChecks[$waitsFor->value]->isReady())
			->map(fn (SetupCheckEnum $waitsFor) => $waitsFor->label());

		return match (true) {
			$setupCheckObject->isReady() => new SetupPointObject($setupCheckObject, SetupPointStateEnum::DONE, $setupCheckObject->detail),
			$isRunning && $this->repairStateObject->setupCheck === $setupCheck => new SetupPointObject($setupCheckObject, SetupPointStateEnum::BUSY, $this->repairStateObject->step ?? __('bezig')),
			$failure !== null => new SetupPointObject($setupCheckObject, SetupPointStateEnum::FAILED, $setupCheckObject->repair === null ? __('niet gelukt') : $setupCheckObject->repair . ' · ' . __('niet gelukt'), $failure, $this->waitingOn($setupCheck, $setupChecks)),
			$blockers->isNotEmpty() || $setupCheckObject->state === CheckStateEnum::WAITING => new SetupPointObject(
				$setupCheckObject,
				SetupPointStateEnum::WAITING,
				$blockers->isEmpty() ? $setupCheckObject->detail : __('wacht op :points', ['points' => $blockers->implode(', ')]),
			),
			$isRunning && $setupCheckObject->canRepair() => new SetupPointObject($setupCheckObject, SetupPointStateEnum::QUEUED, __('staat in de rij')),
			default => new SetupPointObject($setupCheckObject, SetupPointStateEnum::OPEN, $setupCheckObject->detail),
		};
	}

	/**
	 * The names of the open points that wait on this one, in one line.
	 *
	 * @param  array<string, SetupCheckObject>  $setupChecks
	 */
	private function waitingOn(SetupCheckEnum $setupCheck, array $setupChecks): string
	{
		return collect($setupChecks)
			->filter(fn (SetupCheckObject $setupCheckObject) => ! $setupCheckObject->isReady() && in_array($setupCheck, $setupCheckObject->setupCheck->waitsFor(), true))
			->map(fn (SetupCheckObject $setupCheckObject) => $setupCheckObject->setupCheck->label())
			->implode(', ');
	}
}
