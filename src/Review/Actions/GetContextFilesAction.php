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
			ContextChoiceEnum::RULES => GetRuleContextFilesAction::execute($this->projectPath, $this->workingPath),
			ContextChoiceEnum::SKILLS => GetSkillContextFilesAction::execute($this->project),
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
				description: $latestRun?->startedAt === null ? __('nog geen beurt') : __('beurt van :moment', ['moment' => $latestRun->startedAt->toDisplayZone()->format('d-m H:i')]),
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
}
