<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Board\Objects\CardObject;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Review\Actions\GetContextFilesAction;
use Src\Review\Actions\GetLensesAction;
use Src\Review\Enums\ContextChoiceEnum;
use Src\Review\Objects\ContextFileObject;
use Src\Review\Objects\LensEntity;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * The Context tab: what the agent of a task, or one of its lenses, is given,
 * file by file.
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
	 * @return array<string, mixed>
	 */
	private function contextData(CardObject $cardObject, ?WorktreeEntity $worktreeEntity): array
	{
		[$kind, $lens] = explode(':', $this->contextChoice, 2) + [null, null];
		$choice = ContextChoiceEnum::tryFrom((string) $kind) ?? ContextChoiceEnum::AGENT;
		$projectPath = $this->projectPathFor($cardObject);

		$files = GetContextFilesAction::execute(
			$choice,
			$lens,
			$cardObject->id,
			$cardObject->project,
			$projectPath,
			$worktreeEntity?->path ?? $projectPath,
		);

		$openFile = collect($files)->first(fn (ContextFileObject $contextFileObject) => $contextFileObject->key === $this->contextFile)
			?? collect($files)->first(fn (ContextFileObject $contextFileObject) => $contextFileObject->contents !== null);

		return [
			'contextChoices' => [
				__('Alleen voor de agent') => ['agent' => __('Agent · :name', ['name' => $cardObject->agent?->name ?? __('nog geen')])],
				__('Alleen voor een lens') => GetLensesAction::execute($cardObject->project)
					->filter(fn (LensEntity $lensEntity) => $lensEntity->isEnabled)
					->mapWithKeys(fn (LensEntity $lensEntity) => ['lens:' . $lensEntity->name => __('Lens · :name', ['name' => $lensEntity->name])])
					->all(),
				__('Voor allebei') => ['rules' => 'Rules', 'skills' => 'Skills'],
			],
			'contextExplanation' => $choice->explanation(),
			'contextGroups' => collect($files)->groupBy(fn (ContextFileObject $contextFileObject) => $contextFileObject->group ?? '')->all(),
			'contextOpen' => $openFile,
			'contextHtml' => $openFile?->contents === null ? null : resolve(RenderMarkdown::class)->toHtml($openFile->contents),
		];
	}
}
