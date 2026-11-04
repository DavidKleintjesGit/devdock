<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Access\Enums\GitGrantEnum;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\StartPromptAction;
use Src\Prompt\Objects\PromptRunObject;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\RebaseOutcomeEnum;
use Src\Worktree\Objects\RebaseOutcomeObject;

/**
 * What "Conflicten oplossen" does: DevDock begins the rebase on the branch the
 * work merges into, and when it stops on conflicts the agent gets one turn in
 * which it may finish that rebase. A rebase that already stands open goes
 * straight to the agent. Pushing stays with the button.
 *
 * @method static RebaseOutcomeObject execute(string $card, string $project, string $branch, ?string $ticketKey, string $directory)
 */
class ResolveConflictsAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly string $project,
		public readonly string $branch,
		public readonly ?string $ticketKey,
		public readonly string $directory,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): RebaseOutcomeObject
	{
		$isAlreadyOpen = $worktreeRepository->isRebasing($this->directory);

		$rebaseOutcomeObject = $isAlreadyOpen
			? new RebaseOutcomeObject(
				RebaseOutcomeEnum::CONFLICTED,
				(string) $worktreeRepository->getFor($this->project, $this->branch)?->targetBranch,
				$worktreeRepository->getConflictedFiles($this->directory),
			)
			: $worktreeRepository->beginRebase($this->directory);

		if ($rebaseOutcomeObject->outcome !== RebaseOutcomeEnum::CONFLICTED) {
			return $rebaseOutcomeObject;
		}

		StartPromptAction::execute(new PromptRunObject(
			card: $this->card,
			reviewRoundId: null,
			project: $this->project,
			branch: $this->branch,
			directory: null,
			ticketKey: $this->ticketKey,
			sessionUuid: null,
			claimsSession: false,
			prompt: $this->prompt($rebaseOutcomeObject, $isAlreadyOpen),
			gitGrant: GitGrantEnum::REBASE,
		));

		return $rebaseOutcomeObject;
	}

	private function prompt(RebaseOutcomeObject $rebaseOutcomeObject, bool $isAlreadyOpen): string
	{
		$situation = $isAlreadyOpen
			? __('De rebase van :branch op origin/:target staat nog open.', ['branch' => $this->branch, 'target' => $rebaseOutcomeObject->targetBranch])
			: __('DevDock is een rebase van :branch op origin/:target begonnen; die staat stil op conflicten.', ['branch' => $this->branch, 'target' => $rebaseOutcomeObject->targetBranch]);

		return implode("\n\n", array_filter([
			$situation,
			$rebaseOutcomeObject->conflictedFiles === [] ? null : __('Nog niet opgelost: :files', ['files' => implode(', ', $rebaseOutcomeObject->conflictedFiles)]),
			__('Je hebt voor deze beurt toestemming om die rebase af te maken: los elk conflict op, zet het bestand klaar met git add of git rm, en ga verder met git rebase --continue tot de rebase klaar is. Andere git-acties en pushen blijven verboden.'),
			__('Draai daarna de tests van wat je aanraakte en zeg wanneer het klaar is. Lukt het niet, breek dan af met git rebase --abort en leg uit waarom.'),
		]));
	}
}
