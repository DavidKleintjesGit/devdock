<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\DescribeChatAction;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\ContextChoiceEnum;
use Src\Review\Enums\WorkflowStepEnum;
use Src\Review\Objects\ContextFileObject;
use Src\Review\Objects\LensEntity;

/**
 * What an agent or a lens is given in one project, or in every project,
 * before a task adds its own: the standing blocks, the way of working, a
 * lens's assignment, and the rules and skills.
 *
 * @method static array<int, ContextFileObject> execute(ContextChoiceEnum $choice, ?string $lens, ?string $project, ?string $projectPath)
 */
class GetStandardContextFilesAction extends Command
{
	public function __construct(
		public readonly ContextChoiceEnum $choice,
		public readonly ?string $lens,
		// null is the standard, for every project
		public readonly ?string $project,
		public readonly ?string $projectPath,
	) {}

	/**
	 * @return array<int, ContextFileObject>
	 */
	public function handle(AgentContextRepositoryContract $agentContextRepository): array
	{
		return match ($this->choice) {
			ContextChoiceEnum::AGENT => $this->agentFiles($agentContextRepository),
			ContextChoiceEnum::LENS => $this->lensFiles(),
			ContextChoiceEnum::RULES => GetRuleContextFilesAction::execute($this->projectPath, $this->projectPath),
			ContextChoiceEnum::SKILLS => GetSkillContextFilesAction::execute($this->project),
		};
	}

	/**
	 * @return array<int, ContextFileObject>
	 */
	private function agentFiles(AgentContextRepositoryContract $agentContextRepository): array
	{
		$blocks = $agentContextRepository->getBlocks();
		$projectContext = $this->project === null ? null : GetWorkflowSettingAction::execute($this->project)->context;

		return [
			new ContextFileObject(key: 'standing', name: __('Vaste afspraken'), group: __('Voor elke taak'), description: __('wie de agent is en wat hij nooit zonder ja doet'), contents: $blocks['standing'] ?? null),
			new ContextFileObject(key: 'chat', name: __('Chatinstructies'), group: __('Voor elke taak'), description: __('hoe hij mockups en keuzes schrijft'), contents: DescribeChatAction::execute()),
			...array_map(
				fn (WorkflowStepEnum $step) => new ContextFileObject(key: 'step:' . $step->value, name: $step->label(), group: __('Per stap van de taak'), contents: $blocks[$step->value] ?? null),
				WorkflowStepEnum::cases(),
			),
			...($this->project === null ? [] : [new ContextFileObject(
				key: 'project',
				name: __('Werkwijze van dit project'),
				group: __('Dit project'),
				description: $projectContext === null ? __('niet ingevuld') : null,
				contents: $projectContext,
			)]),
		];
	}

	/**
	 * @return array<int, ContextFileObject>
	 */
	private function lensFiles(): array
	{
		$lensEntity = collect(GetLensesAction::execute($this->project))
			->first(fn (LensEntity $lensEntity) => $lensEntity->name === $this->lens);

		if ($lensEntity === null) {
			return [];
		}

		return [
			new ContextFileObject(
				key: 'definition',
				name: __('Lens :name', ['name' => $lensEntity->name]),
				description: __('omschrijving, paden, regels, commando'),
				contents: implode("\n", array_filter([
					$lensEntity->description,
					'',
					// in code marks, or markdown reads the ** of a glob as bold
					'- ' . __('Kijkt naar: :paths', ['paths' => implode(', ', array_map(fn (string $path) => '`' . $path . '`', $lensEntity->paths))]),
					'- ' . __('Toetst tegen: :rules', ['rules' => $lensEntity->rules === [] ? __('geen regelbestand') : implode(', ', array_map(fn (string $rule) => '`' . $rule . '`', $lensEntity->rules))]),
					'- ' . __('Draait: :command', ['command' => $lensEntity->command === null ? __('niets') : '`' . $lensEntity->command . '`']),
				], fn (?string $line) => $line !== null)),
			),
			new ContextFileObject(
				key: 'assignment',
				name: __('Lensopdracht'),
				description: __('zo krijgt de lens hem; een taak vult de bestanden en het vorige rapport in'),
				contents: ComposeLensContextAction::execute($lensEntity, [__('<de bestanden die de taak raakte>')], null, $lensEntity->rules),
			),
		];
	}
}
