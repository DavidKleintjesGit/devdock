<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetBranchesAction;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\BranchPlaceEnum;
use Src\Worktree\Objects\BranchChoiceObject;

/**
 * The branches a task can be set up on in one project: the ones here, and the
 * ones that only exist on origin. A branch with the ticket number in its name
 * comes first.
 *
 * @method static list<BranchChoiceObject> execute(string $project, ?string $ticketKey)
 */
class GetBranchChoicesAction extends Command
{
	/**
	 * The main line is where branches are cut from, never one to work on.
	 */
	private const MAIN_BRANCHES = ['master', 'main', 'HEAD'];

	private const REMEMBER_SECONDS = 30;

	public function __construct(
		public readonly string $project,
		public readonly ?string $ticketKey = null,
	) {}

	/**
	 * @return list<BranchChoiceObject>
	 */
	public function handle(WorktreeRepositoryContract $worktreeRepository): array
	{
		// elke keuze in de lijst tekent het paneel opnieuw; git hoeft dat niet elke keer opnieuw te vertellen
		return Cache::remember(
			'worktree.branch-choices.' . md5($this->project . '|' . $this->ticketKey),
			self::REMEMBER_SECONDS,
			fn () => $this->choices($worktreeRepository),
		);
	}

	/**
	 * @return list<BranchChoiceObject>
	 */
	private function choices(WorktreeRepositoryContract $worktreeRepository): array
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)
			?->path;

		if ($projectPath === null) {
			return [];
		}

		$places = $worktreeRepository->getPlaces($projectPath);
		$local = array_values(array_diff(GetBranchesAction::execute($this->project), self::MAIN_BRANCHES));
		$remote = collect($worktreeRepository->getRemoteBranches($projectPath))
			->map(fn (string $name) => substr($name, strlen('origin/')))
			->reject(fn (string $name) => in_array($name, $local, true) || in_array($name, self::MAIN_BRANCHES, true))
			->values()
			->all();

		return collect([
			...array_map(fn (string $name) => $this->choiceFor($name, $places, $projectPath, $worktreeRepository), $local),
			...array_map(fn (string $name) => new BranchChoiceObject($name, BranchPlaceEnum::REMOTE, null, $this->matches($name)), $remote),
		])
			->sortBy(fn (BranchChoiceObject $branchChoiceObject) => $branchChoiceObject->matchesTicket ? 0 : 1)
			->values()
			->all();
	}

	/**
	 * @param  array<string, string>  $places
	 */
	private function choiceFor(string $name, array $places, string $projectPath, WorktreeRepositoryContract $worktreeRepository): BranchChoiceObject
	{
		$known = $worktreeRepository->getFor($this->project, $name);
		$path = $known !== null && is_dir($known->path) ? $known->path : ($places[$name] ?? null);

		$place = match (true) {
			$path === null => BranchPlaceEnum::LOCAL,
			$this->samePath($path, $projectPath) => BranchPlaceEnum::PROJECT,
			default => BranchPlaceEnum::WORKTREE,
		};

		return new BranchChoiceObject($name, $place, $path, $this->matches($name));
	}

	private function matches(string $name): bool
	{
		return $this->ticketKey !== null && stripos($name, $this->ticketKey) !== false;
	}

	private function samePath(string $left, string $right): bool
	{
		return strtolower(rtrim(str_replace('\\', '/', $left), '/')) === strtolower(rtrim(str_replace('\\', '/', $right), '/'));
	}
}
