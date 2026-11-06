<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Board\Enums\CardSubtabEnum;
use Src\Board\Objects\CardObject;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Review\Actions\BuildContextTreeAction;
use Src\Review\Actions\GetContextFilesAction;
use Src\Review\Actions\GetLensesAction;
use Src\Review\Actions\GetStandardContextFilesAction;
use Src\Review\Enums\ContextAudienceEnum;
use Src\Review\Enums\ContextChoiceEnum;
use Src\Review\Objects\ContextFileObject;
use Src\Review\Objects\LensEntity;

/**
 * Context: what the agent, or one lens, is given, file by file. On a card for
 * that task; in Workflows for a project or for every project.
 */
trait WithContext
{
	/**
	 * The picked choice: agent, rules, skills, or lens:<name> for one lens.
	 */
	public string $contextChoice = 'agent';

	public ?string $contextFile = null;

	public function updatedContextChoice(): void
	{
		$this->contextFile = null;
	}

	public function openContextFile(string $key): void
	{
		$this->contextFile = $key;
	}

	/**
	 * Picks the agent on the Context subtab of Chat and a lens on the Lenzen subtab of Review.
	 */
	private function fitContextChoice(?string $project, ?CardSubtabEnum $cardSubtab): void
	{
		$isLensChoice = str_starts_with($this->contextChoice, 'lens:');

		if ($cardSubtab === CardSubtabEnum::CONTEXT && $isLensChoice) {
			$this->contextChoice = 'agent';
			$this->contextFile = null;
		}

		if ($cardSubtab === CardSubtabEnum::LENSES && ! $isLensChoice) {
			$lensEntity = GetLensesAction::execute($project)->first(fn (LensEntity $lensEntity) => $lensEntity->isEnabled);
			$this->contextChoice = $lensEntity === null ? 'agent' : 'lens:' . $lensEntity->name;
			$this->contextFile = null;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cardContextData(CardObject $cardObject, ?string $projectPath, ?string $workingPath, CardSubtabEnum $cardSubtab): array
	{
		[$choice, $lens] = $this->pickedContext();
		$own = $cardSubtab === CardSubtabEnum::LENSES ? ContextChoiceEnum::LENS : ContextChoiceEnum::AGENT;
		$choices = $own === ContextChoiceEnum::LENS && ($choice !== ContextChoiceEnum::LENS || $lens === null)
			? [ContextChoiceEnum::RULES, ContextChoiceEnum::SKILLS]
			: [$own, ContextChoiceEnum::RULES, ContextChoiceEnum::SKILLS];

		$entries = collect($choices)
			->flatMap(fn (ContextChoiceEnum $contextChoice) => collect(GetContextFilesAction::execute($contextChoice, $lens, $cardObject->id, $cardObject->project, $projectPath, $workingPath))
				->map(fn (ContextFileObject $contextFileObject) => ['file' => $contextFileObject, 'audience' => ContextAudienceEnum::of($contextChoice)]))
			->values();
		$openEntry = $entries->first(fn (array $entry) => $entry['file']->key === $this->contextFile)
			?? $entries->first(fn (array $entry) => $entry['file']->contents !== null);

		return [
			'contextTree' => BuildContextTreeAction::execute($entries->all(), $projectPath, $workingPath),
			'contextCount' => $entries->count(),
			'contextExplanation' => $own === ContextChoiceEnum::LENS
				? __('Wat deze lens leest in de laatste ronde, met wie het nog meer krijgt.')
				: __('Wat de agent bij elk bericht leest, met wie het nog meer krijgt.'),
			'contextLenses' => $own === ContextChoiceEnum::LENS ? $this->contextChoices($cardObject->project, '')[__('Alleen voor een lens')] : [],
			'contextOpen' => $openEntry['file'] ?? null,
			'contextOpenAudience' => $openEntry['audience'] ?? null,
			'contextHtml' => ($openEntry['file'] ?? null)?->contents === null ? null : resolve(RenderMarkdown::class)->toHtml($openEntry['file']->contents),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function projectContextData(?string $project, ?string $projectPath): array
	{
		[$choice, $lens] = $this->pickedContext();

		return $this->contextView(
			GetStandardContextFilesAction::execute($choice, $lens, $project, $projectPath),
			$choice->explanationOutsideTask(),
			$this->contextChoices($project, __('Agent')),
		);
	}

	/**
	 * @return array{0: ContextChoiceEnum, 1: ?string}
	 */
	private function pickedContext(): array
	{
		[$kind, $lens] = explode(':', $this->contextChoice, 2) + [null, null];

		return [ContextChoiceEnum::tryFrom((string) $kind) ?? ContextChoiceEnum::AGENT, $lens];
	}

	/**
	 * The options of the picker, in three groups.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function contextChoices(?string $project, string $agentLabel): array
	{
		return [
			__('Alleen voor de agent') => ['agent' => $agentLabel],
			__('Alleen voor een lens') => GetLensesAction::execute($project)
				->filter(fn (LensEntity $lensEntity) => $lensEntity->isEnabled)
				->mapWithKeys(fn (LensEntity $lensEntity) => ['lens:' . $lensEntity->name => __('Lens · :name', ['name' => $lensEntity->name])])
				->all(),
			__('Voor allebei') => ['rules' => 'Rules', 'skills' => 'Skills'],
		];
	}

	/**
	 * @param  array<int, ContextFileObject>  $files
	 * @param  array<string, array<string, string>>  $choices
	 * @return array<string, mixed>
	 */
	private function contextView(array $files, string $explanation, array $choices): array
	{
		$openFile = collect($files)->first(fn (ContextFileObject $contextFileObject) => $contextFileObject->key === $this->contextFile)
			?? collect($files)->first(fn (ContextFileObject $contextFileObject) => $contextFileObject->contents !== null);

		return [
			'contextChoices' => $choices,
			'contextExplanation' => $explanation,
			'contextCount' => count($files),
			'contextGroups' => collect($files)->groupBy(fn (ContextFileObject $contextFileObject) => $contextFileObject->group ?? '')->all(),
			'contextOpen' => $openFile,
			'contextHtml' => $openFile?->contents === null ? null : resolve(RenderMarkdown::class)->toHtml($openFile->contents),
		];
	}
}
