<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Board\Enums\CardSubtabEnum;
use Src\Board\Objects\CardObject;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Review\Actions\BuildContextGroupsAction;
use Src\Review\Actions\GetContextFilesAction;
use Src\Review\Actions\GetLensesAction;
use Src\Review\Actions\GetStandardContextFilesAction;
use Src\Review\Enums\ContextAudienceEnum;
use Src\Review\Enums\ContextChoiceEnum;
use Src\Review\Enums\ContextScopeEnum;
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

	/**
	 * Which sources of the picked agent or lens are on screen; see ContextScopeEnum.
	 */
	public string $contextScope = 'given';

	public function updatedContextChoice(): void
	{
		$this->contextFile = null;
	}

	public function setContextScope(string $scope): void
	{
		$this->contextScope = (ContextScopeEnum::tryFrom($scope) ?? ContextScopeEnum::GIVEN)->value;
		$this->contextFile = null;
	}

	public function closeContextFile(): void
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
		$lensNames = GetLensesAction::execute($cardObject->project)
			->filter(fn (LensEntity $lensEntity) => $lensEntity->isEnabled)
			->map(fn (LensEntity $lensEntity) => $lensEntity->name)
			->values()
			->all();
		$subjectLens = $choice === ContextChoiceEnum::LENS && in_array($lens, $lensNames, true) ? $lens : null;
		$filesOf = fn (ContextChoiceEnum $contextChoice, ?string $lensName) => GetContextFilesAction::execute($contextChoice, $lensName, $cardObject->id, $cardObject->project, $projectPath, $workingPath);

		$shared = collect([ContextChoiceEnum::RULES, ContextChoiceEnum::SKILLS])
			->flatMap(fn (ContextChoiceEnum $contextChoice) => collect($filesOf($contextChoice, null))
				->map(fn (ContextFileObject $contextFileObject) => ['file' => $contextFileObject, 'audience' => ContextAudienceEnum::BOTH, 'own' => false]));
		$agentEntries = collect($filesOf(ContextChoiceEnum::AGENT, null))
			->map(fn (ContextFileObject $contextFileObject) => ['file' => $contextFileObject, 'audience' => ContextAudienceEnum::AGENT, 'own' => $subjectLens === null]);
		$lensEntries = fn (string $lensName) => collect($filesOf(ContextChoiceEnum::LENS, $lensName))
			->map(fn (ContextFileObject $contextFileObject) => [
				'file' => $this->forLens($contextFileObject, $lensName),
				'audience' => ContextAudienceEnum::LENS,
				'own' => $lensName === $subjectLens,
			]);

		$own = $subjectLens === null ? $agentEntries : $lensEntries($subjectLens);
		$byScope = [
			ContextScopeEnum::GIVEN->value => $own->concat($shared)->values(),
			ContextScopeEnum::OWN->value => $own->values(),
			ContextScopeEnum::ALL->value => $agentEntries
				->concat(collect($lensNames)->flatMap(fn (string $lensName) => $lensEntries($lensName)))
				->concat($shared)
				->values(),
		];
		$scope = ContextScopeEnum::tryFrom($this->contextScope) ?? ContextScopeEnum::GIVEN;
		$entries = $byScope[$scope->value];
		$openEntry = $entries->first(fn (array $entry) => $entry['file']->key === $this->contextFile);
		$layout = BuildContextGroupsAction::execute($entries->all(), $projectPath, $workingPath);

		return [
			// everything starts folded; only the group of the source you are reading stays open
			'contextOrigins' => collect($layout['groups'])->map(fn (array $group) => [
				...$group,
				'isOpen' => $openEntry !== null && collect($group['files'])->contains(fn (array $file) => $file['file']->key === $openEntry['file']->key),
			])->all(),
			'contextShare' => $layout['share'],
			'contextTokens' => $layout['tokensLabel'],
			'contextCount' => $entries->count(),
			'contextScope' => $scope,
			'contextScopes' => collect(ContextScopeEnum::cases())->map(fn (ContextScopeEnum $contextScope) => [
				'case' => $contextScope,
				'count' => $byScope[$contextScope->value]->count(),
			])->all(),
			'contextSubjects' => [
				'agent' => __('Agent'),
				...collect($lensNames)->mapWithKeys(fn (string $lensName) => ['lens:' . $lensName => __('Lens · :name', ['name' => $lensName])])->all(),
			],
			'contextSubjectLabel' => $subjectLens === null ? __('de agent') : __('lens :name', ['name' => $subjectLens]),
			'contextOpen' => $openEntry['file'] ?? null,
			'contextOpenAudience' => $openEntry['audience'] ?? null,
			'contextOpenOwn' => $openEntry['own'] ?? false,
			'contextOpenTokens' => $openEntry === null ? null : collect($layout['groups'])->flatMap(fn (array $group) => $group['files'])->first(fn (array $file) => $file['file']->key === $openEntry['file']->key)['tokensLabel'] ?? null,
			'contextHtml' => ($openEntry['file'] ?? null)?->contents === null ? null : resolve(RenderMarkdown::class)->toHtml($openEntry['file']->contents),
		];
	}

	/**
	 * A source of one lens under a key and name of its own, so the sources of several lenses can stand side by side.
	 */
	private function forLens(ContextFileObject $contextFileObject, string $lensName): ContextFileObject
	{
		return new ContextFileObject(
			key: 'lens:' . $lensName . ':' . $contextFileObject->key,
			name: $lensName . ' · ' . $contextFileObject->name,
			group: $contextFileObject->group,
			description: $contextFileObject->description,
			path: $contextFileObject->path,
			contents: $contextFileObject->contents,
			tab: $contextFileObject->tab,
		);
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
