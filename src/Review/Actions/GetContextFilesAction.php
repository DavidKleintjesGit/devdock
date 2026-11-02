<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\DescribeChatAction;
use Src\Prompt\Actions\GetLatestPromptRunAction;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\ContextChoiceEnum;
use Src\Review\Objects\ContextFileObject;
use Src\Review\Objects\ReviewResultEntity;
use Src\Rule\Actions\GetGlobalRulesAction;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Skill\Actions\GetSkillContentsAction;
use Src\Skill\Actions\GetSkillsAction;
use Src\Skill\Enums\SkillScopeEnum;
use Src\Skill\Objects\SkillEntity;

/**
 * What an agent or a lens on one task is given, file by file: what only the
 * agent gets, what only one lens gets, or the rules and skills both get.
 *
 * @method static array<int, ContextFileObject> execute(ContextChoiceEnum $choice, ?string $lens, string $card, ?string $project, ?string $projectPath, ?string $workingPath)
 */
class GetContextFilesAction extends Command
{
	public function __construct(
		public readonly ContextChoiceEnum $choice,
		public readonly ?string $lens,
		public readonly string $card,
		public readonly ?string $project,
		public readonly ?string $projectPath,
		// the working copy the task runs in, or the project itself when it has none
		public readonly ?string $workingPath,
	) {}

	/**
	 * @return array<int, ContextFileObject>
	 */
	public function handle(AgentContextRepositoryContract $agentContextRepository): array
	{
		return match ($this->choice) {
			ContextChoiceEnum::AGENT => $this->agentFiles($agentContextRepository),
			ContextChoiceEnum::LENS => $this->lensFiles($agentContextRepository),
			ContextChoiceEnum::RULES => $this->ruleFiles(),
			ContextChoiceEnum::SKILLS => $this->skillFiles(),
		};
	}

	/**
	 * @return array<int, ContextFileObject>
	 */
	private function agentFiles(AgentContextRepositoryContract $agentContextRepository): array
	{
		$latestRun = GetLatestPromptRunAction::execute($this->card);

		return [
			new ContextFileObject(
				key: 'task',
				name: __('Taakbestand'),
				description: __('wie hij is, waar hij staat, wat nu gevraagd wordt'),
				path: $agentContextRepository->relativePath(),
				contents: ($this->workingPath === null ? null : $agentContextRepository->getAgentContext($this->workingPath))
					?? ComposeAgentContextAction::execute($this->card, $this->workingPath),
			),
			new ContextFileObject(
				key: 'chat',
				name: __('Chatinstructies'),
				description: __('hoe hij mockups en keuzes schrijft'),
				contents: DescribeChatAction::execute(),
			),
			new ContextFileObject(
				key: 'last-turn',
				name: __('Opdracht van de laatste beurt'),
				description: $latestRun?->startedAt === null ? __('nog geen beurt') : __('beurt van :moment', ['moment' => $latestRun->startedAt->format('d-m H:i')]),
				contents: $latestRun?->prompt,
			),
			new ContextFileObject(
				key: 'conversation',
				name: __('Gesprek'),
				description: __('alle eerdere beurten, hervat met de sessiesleutel'),
				tab: 'Agent',
			),
		];
	}

	/**
	 * @return array<int, ContextFileObject>
	 */
	private function lensFiles(AgentContextRepositoryContract $agentContextRepository): array
	{
		$lens = (string) $this->lens;
		$latestRound = collect(GetRoundsAction::execute($this->card))->last();
		$result = collect($latestRound?->results ?? [])->first(fn (ReviewResultEntity $resultEntity) => $resultEntity->lensName === $lens);
		$assignment = $this->workingPath === null ? null : $agentContextRepository->getLensContext($this->workingPath, $lens);

		return [
			new ContextFileObject(
				key: 'assignment',
				name: __('Lensopdracht'),
				description: $assignment === null ? __('deze lens keek hier nog niet') : __('bestanden, regels, commando, formaat'),
				path: $agentContextRepository->lensPath($lens),
				contents: $assignment,
			),
			new ContextFileObject(
				key: 'report',
				name: __('Laatste rapport'),
				description: $result === null ? __('nog geen ronde') : __('ronde :number · :verdict', ['number' => $latestRound->number, 'verdict' => $result->verdict->label()]),
				contents: $result?->report,
			),
			new ContextFileObject(
				key: 'instruction',
				name: __('Opdracht van de beurt'),
				description: __('één regel die naar de lensopdracht wijst'),
				contents: WriteLensContextAction::assignmentFor($agentContextRepository->lensPath($lens)),
			),
		];
	}

	/**
	 * @return array<int, ContextFileObject>
	 */
	private function ruleFiles(): array
	{
		$ruleFileSystemRepository = resolve(RuleFileSystemRepositoryContract::class);
		$global = GetGlobalRulesAction::execute();
		$files = [];

		foreach ($global['files'] as $path => $contents) {
			$files[] = new ContextFileObject(key: 'global:' . $path, name: basename($path), group: __('Globaal'), path: '~/.claude/' . $path, contents: $contents);
		}

		// Claude Code walks up from a working copy and reads the instructions of the checkout around it too
		if ($this->workingPath !== null && $this->projectPath !== null && $this->workingPath !== $this->projectPath && str_starts_with($this->workingPath, $this->projectPath . '/')) {
			foreach ($ruleFileSystemRepository->getLoadedFiles($this->projectPath) as $path => $contents) {
				if (! str_contains($path, '/')) {
					$files[] = new ContextFileObject(key: 'checkout:' . $path, name: $path, group: __('Uit de checkout eromheen'), path: $path, contents: $contents);
				}
			}
		}

		$projectFiles = collect($ruleFileSystemRepository->getLoadedFiles((string) ($this->workingPath ?? $this->projectPath)))
			->map(fn (string $contents, string $path) => new ContextFileObject(
				key: 'project:' . $path,
				name: str_starts_with($path, '.claude/rules/') ? substr($path, strlen('.claude/rules/')) : $path,
				group: $path === 'CLAUDE.local.md' || str_contains($path, '/local/') ? __('Lokaal, alleen voor jou') : __('Project'),
				path: $path,
				contents: $contents,
			))
			->sortBy(fn (ContextFileObject $contextFileObject) => $contextFileObject->group === __('Project') ? 0 : 1)
			->values()
			->all();

		$files = [...$files, ...$projectFiles];

		$memoryIndex = $this->projectPath === null ? null : $ruleFileSystemRepository->getMemoryIndex($this->projectPath);

		if ($memoryIndex !== null) {
			$files[] = new ContextFileObject(key: 'memory', name: 'MEMORY.md', group: __('Geheugen'), description: __('de index; de notities zelf leest Claude als hij ze nodig heeft'), contents: $memoryIndex);
		}

		return $files;
	}

	/**
	 * @return array<int, ContextFileObject>
	 */
	private function skillFiles(): array
	{
		return collect(GetSkillsAction::execute()->toArray())
			->filter(fn (SkillEntity $skillEntity) => $skillEntity->scope !== SkillScopeEnum::PROJECT || $skillEntity->project === $this->project)
			->sortBy(fn (SkillEntity $skillEntity) => array_search($skillEntity->scope, SkillScopeEnum::cases(), true))
			->map(fn (SkillEntity $skillEntity) => new ContextFileObject(
				key: 'skill:' . $skillEntity->skillId->id,
				name: $skillEntity->name,
				group: $skillEntity->scope->label(),
				description: $skillEntity->description,
				path: $skillEntity->path,
				contents: GetSkillContentsAction::execute($skillEntity->skillId),
			))
			->values()
			->all();
	}
}
