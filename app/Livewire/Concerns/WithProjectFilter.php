<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Collection;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Actions\GetWorkProjectsAction;

/**
 * The project choice is one for the whole board: pick projects on the board
 * and the sessions, the rules and the skills show that same selection. That
 * is why it lives in the session and not per page.
 *
 * No choice at all shows everything; an empty choice shows only what has no
 * project, or nothing when that is switched off too.
 */
trait WithProjectFilter
{
	/** @var array<int, string>|null */
	public ?array $projects = null;

	/**
	 * Whether a task without a project stays in view while you filter on projects.
	 */
	public bool $withoutProject = true;

	public string $projectQuery = '';

	public function mountWithProjectFilter(): void
	{
		$this->projects = session()->has('devdock.project-choice')
			? session('devdock.project-choice')
			: $this->workProjectNames();
		$this->withoutProject = (bool) session('devdock.without-project', true);
		$this->remember();
	}

	public function toggleProject(string $project): void
	{
		$projects = $this->projects ?? [];

		$this->projects = in_array($project, $projects, true)
			? array_values(array_diff($projects, [$project]))
			: [...$projects, $project];

		$this->remember();
	}

	public function onlyProject(string $project): void
	{
		$this->projects = [$project];
		$this->withoutProject = false;
		$this->remember();
	}

	public function toggleWithoutProject(): void
	{
		$this->projects ??= [];
		$this->withoutProject = ! $this->withoutProject;
		$this->remember();
	}

	public function selectWorkProjects(): void
	{
		$this->projects = $this->workProjectNames();
		$this->remember();
	}

	public function clearProjects(): void
	{
		$this->projects = null;
		$this->remember();
	}

	public function deselectProjects(): void
	{
		$this->projects = [];
		$this->withoutProject = false;
		$this->remember();
	}

	/**
	 * The projects to choose from, with how many each one has. Projects from the
	 * Bitbucket workspace come first, the rest below; what is in the search
	 * field filters both.
	 *
	 * @param  Collection<string, int>  $counts
	 * @return Collection<int, array{0: string, 1: int, 2: bool}>
	 */
	protected function projectOptions(Collection $counts): Collection
	{
		$work = $this->workProjectNames();

		// every folder on disk is in the list, cards or no cards; the folders lead,
		// the counters only say how much is on screen now
		$counts = GetProjectsAction::execute()
			->pluck('name')
			->mapWithKeys(fn (string $name) => [$name => 0])
			->merge($counts);

		return $counts
			->sortDesc()
			->map(fn (int $count, string $name) => [$name, $count, in_array($name, $work, true)])
			->values()
			->when(filled($this->projectQuery), fn (Collection $all) => $all->filter(
				fn (array $option) => str_contains(strtolower($option[0]), strtolower($this->projectQuery)),
			)->values())
			->sortByDesc(fn (array $option) => $option[2])
			->values();
	}

	/**
	 * The chosen projects as separate chips, each with the way to let it go.
	 *
	 * @return array<int, array{label: string, filter: string}>
	 */
	protected function projectChips(): array
	{
		return array_map(fn (string $name) => [
			'label' => __('Project: :name', ['name' => $name]),
			'filter' => 'project:' . $name,
		], $this->projects ?? []);
	}

	protected function hasProjectFilter(): bool
	{
		return $this->projects !== null;
	}

	/**
	 * Whether something with this project stays in view under the current choice.
	 */
	protected function showsProject(?string $project): bool
	{
		if ($this->projects === null) {
			return true;
		}

		return $project === null ? $this->withoutProject : in_array($project, $this->projects, true);
	}

	/**
	 * The projects that belong to your own workspace; that is the choice every
	 * page starts with.
	 *
	 * @return array<int, string>
	 */
	private function workProjectNames(): array
	{
		return GetWorkProjectsAction::execute()->pluck('name')->values()->all();
	}

	private function remember(): void
	{
		session([
			'devdock.project-choice' => $this->projects,
			'devdock.without-project' => $this->withoutProject,
		]);
	}
}
