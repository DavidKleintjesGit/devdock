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
 */
trait WithProjectFilter
{
	/** @var array<int, string> */
	public array $projects = [];

	public string $projectQuery = '';

	public function mountWithProjectFilter(): void
	{
		$this->projects = session('devdock.projects', $this->workProjectNames());
		session(['devdock.projects' => $this->projects]);
	}

	public function toggleProject(string $project): void
	{
		$this->projects = in_array($project, $this->projects, true)
			? array_values(array_diff($this->projects, [$project]))
			: [...$this->projects, $project];

		$this->remember();
	}

	public function selectWorkProjects(): void
	{
		$this->projects = $this->workProjectNames();
		$this->remember();
	}

	public function clearProjects(): void
	{
		$this->projects = [];
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
		], $this->projects);
	}

	protected function hasProjectFilter(): bool
	{
		return $this->projects !== [];
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
		session(['devdock.projects' => $this->projects]);
	}
}
