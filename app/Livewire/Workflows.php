<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithProjectFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Session;
use Livewire\Component;
use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\Project\Actions\GetProjectsAction;
use Src\Review\Actions\GetAgentContextBlocksAction;
use Src\Review\Actions\GetLensesAction;
use Src\Review\Actions\GetRoundsAction;
use Src\Review\Actions\ResolveWorkflowStepAction;
use Src\Review\Actions\SaveAgentContextBlocksAction;
use Src\Review\Actions\ToggleLensAction;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\WorkflowStepEnum;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundEntity;

class Workflows extends Component
{
	use WithProjectFilter;

	#[Session(key: 'devdock.workflows.lensproject')]
	public string $lensProject = '';

	public string $filter = '';

	/** @var array<string, string> */
	public array $context = [];

	public ?string $contextSaved = null;

	public function mount(): void
	{
		$this->context = GetAgentContextBlocksAction::execute();

		if ($this->lensProject === '') {
			$this->lensProject = $this->projects[0] ?? '';
		}
	}

	/**
	 * Puts a lens in or out of the rounds of the project this page is set to.
	 */
	public function toggleLens(int $lens, bool $isEnabled): void
	{
		if ($this->lensProject === '') {
			return;
		}

		ToggleLensAction::execute(new LensId(id: $lens), $this->lensProject, $isEnabled);
	}

	/**
	 * Writes the way of working down. It lands in every working copy at the
	 * next round, so a change here reaches the agents by itself.
	 */
	public function saveContext(): void
	{
		SaveAgentContextBlocksAction::execute($this->context);

		$this->context = GetAgentContextBlocksAction::execute();
		$this->contextSaved = __('Opgeslagen. Elke werkkopie krijgt dit bij de volgende ronde.');
	}

	public function setFilter(string $filter): void
	{
		$this->filter = $this->filter === $filter ? '' : $filter;
	}

	public function render(): View
	{
		$workflows = $this->workflows();

		return view('livewire.workflows', [
			'steps' => WorkflowStepEnum::cases(),
			'workflows' => $this->filtered($workflows),
			'total' => $workflows->count(),
			'perStep' => $this->perStep($workflows),
			'stuck' => $workflows->filter(fn (array $workflow) => $this->isStuck($workflow))->count(),
			'lenses' => GetLensesAction::execute($this->lensProject ?: null),
			'lensProjects' => GetProjectsAction::execute()->pluck('name')->sort()->values(),
			'maxRounds' => (int) config('devdock.review.max_rounds'),
			'contextPath' => resolve(AgentContextRepositoryContract::class)->relativePath(),
			'projectOptions' => $this->projectOptions(collect()),
			'projectTotal' => $workflows->count(),
		]);
	}

	/**
	 * Every task that has left the building stage, with where it stands and
	 * what its last round made of it.
	 *
	 * @return Collection<int, array{card: CardObject, step: WorkflowStepEnum, round: ?ReviewRoundEntity}>
	 */
	private function workflows(): Collection
	{
		return collect(BuildBoardAction::execute())
			->when($this->hasProjectFilter(), fn (Collection $cards) => $cards->filter(
				fn (CardObject $cardObject) => in_array($cardObject->project, $this->projects, true),
			))
			->map(function (CardObject $cardObject): array {
				$round = collect(GetRoundsAction::execute($cardObject->id))->last();

				return [
					'card' => $cardObject,
					'round' => $round,
					'rounds' => collect(GetRoundsAction::execute($cardObject->id))->count(),
					'step' => ResolveWorkflowStepAction::execute($cardObject, $round),
				];
			})
			->filter(fn (array $workflow) => $workflow['round'] !== null
				|| $workflow['card']->agent !== null
				|| $workflow['card']->activePullRequest !== null)
			->sortByDesc(fn (array $workflow) => $workflow['round']?->startedAt ?? $workflow['card']->changedAt)
			->values();
	}

	/**
	 * @param  Collection<int, array<string, mixed>>  $workflows
	 * @return Collection<int, array<string, mixed>>
	 */
	private function filtered(Collection $workflows): Collection
	{
		if ($this->filter === 'stuck') {
			return $workflows->filter(fn (array $workflow) => $this->isStuck($workflow))->values();
		}

		$step = WorkflowStepEnum::tryFrom($this->filter);

		return $step === null
			? $workflows->reject(fn (array $workflow) => $workflow['step'] === WorkflowStepEnum::MERGED)->values()
			: $workflows->filter(fn (array $workflow) => $workflow['step'] === $step)->values();
	}

	/**
	 * How many tasks stand on each step, so the filter says what it will give
	 * you before you press it.
	 *
	 * @param  Collection<int, array<string, mixed>>  $workflows
	 * @return array<string, int>
	 */
	private function perStep(Collection $workflows): array
	{
		$counts = [];

		foreach (WorkflowStepEnum::cases() as $step) {
			$counts[$step->value] = $workflows
				->filter(fn (array $workflow) => $workflow['step'] === $step)
				->count();
		}

		return $counts;
	}

	/**
	 * A task is stuck when the lenses kept finding points until the threshold
	 * ran out, so it is waiting on you and not on an agent.
	 */
	private function isStuck(array $workflow): bool
	{
		return $workflow['round']?->status === RoundStatusEnum::FAILED
			&& $workflow['rounds'] >= (int) config('devdock.review.max_rounds');
	}
}
