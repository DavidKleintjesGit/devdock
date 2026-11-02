<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\WorkflowStepEnum;

/**
 * The context of one task, word for word as the agent reads it. Writing it
 * down is a step of its own, so the same text can be shown on the card.
 *
 * @method static ?string execute(string $card, ?string $worktreePath)
 */
class ComposeAgentContextAction extends Command
{
	/**
	 * What every block may put the agent's own name in.
	 */
	private const NAME = '{name}';

	public function __construct(
		public readonly string $card,
		public readonly ?string $worktreePath,
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): ?string
	{
		$cardObject = collect(BuildBoardAction::execute())
			->first(fn (CardObject $cardObject) => $cardObject->id === $this->card);

		if ($cardObject === null) {
			return null;
		}

		$step = ResolveWorkflowStepAction::execute(
			$cardObject,
			collect(GetRoundsAction::execute($this->card))->last(),
		);

		$blocks = $agentContextRepository->getBlocks();
		$name = $cardObject->agent?->name ?? __('the agent');

		return str_replace(self::NAME, $name, implode("\n\n", array_filter([
			'# DevDock — the task you work in',
			$blocks['standing'] ?? '',
			$this->whereYouAre($cardObject),
			$this->whereToLookThingsUp($cardObject),
			$this->theRoute($step),
			$this->whereTheReportsAre($agentContextRepository),
			"## What is asked of you now\n\n" . ($blocks[$step->value] ?? ''),
			$this->forThisProject($cardObject->project),
		])));
	}

	/**
	 * What the project adds to the way of working. It comes after the
	 * standard and never replaces it.
	 */
	private function forThisProject(?string $project): ?string
	{
		$context = GetWorkflowSettingAction::execute($project)->context;

		return $context === null ? null : "## In this project\n\n" . $context;
	}

	/**
	 * What the agent may read outside the code. The tools that write there are
	 * denied to it in ClaudeProcessRepository; this tells it what is open.
	 */
	private function whereToLookThingsUp(CardObject $cardObject): string
	{
		return implode("\n", [
			'## Where to look things up',
			'',
			'You may look up context outside the code, to read only:',
			'- the Jira ticket' . ($cardObject->ticketKey === null ? '' : ' ' . $cardObject->ticketKey) . ' and the tickets linked to it, with getJiraIssue and searchJiraIssuesUsingJql;',
			'- earlier pull requests in Bitbucket, their comments and their commits: find the operation with discover, then call it with executeRead;',
			'- why a line is the way it is, with git log and git blame in this working copy.',
			'',
			'Writing to Jira, Confluence or Bitbucket is closed to you. When something should go there, put it to David.',
		]);
	}

	/**
	 * Where the lenses leave what they made of a round. They are not loaded
	 * for you: you open the one you need, which is why they are named after
	 * the lens that wrote it.
	 */
	private function whereTheReportsAre(AgentContextRepositoryContract $agentContextRepository): string
	{
		return implode("\n", [
			'## What the lenses left',
			'',
			'Every lens that judged this task writes a report of its own in '
. dirname($agentContextRepository->reportFolder($this->card, 1)) . ', a folder per round',
			'(' . basename($agentContextRepository->reportFolder($this->card, 1)) . ' and so on) with one file per lens, named after it:',
			'approved, with findings, or not looked at. The highest round is the one that just ran.',
		]);
	}

	private function whereYouAre(CardObject $cardObject): string
	{
		$lines = [
			'- You: ' . ($cardObject->agent?->name ?? 'not opened yet'),
			'- Task: ' . ($cardObject->ticketKey ?? $cardObject->title),
			'- Project: ' . ($cardObject->project ?? 'unknown'),
			'- Branch: ' . ($cardObject->branch ?? 'none yet'),
			'- Working copy: ' . ($this->worktreePath ?? 'no folder of its own yet'),
		];

		if ($cardObject->agent !== null) {
			$lines[] = '- Your key: ' . $cardObject->agent->sessionUuid;
		}

		if ($cardObject->activePullRequest !== null) {
			$lines[] = '- Pull request: ' . $cardObject->activePullRequest;
		}

		return "## Where you stand\n\n" . implode("\n", $lines);
	}

	/**
	 * The whole path, so the agent knows what comes after this step and not
	 * only what it is doing now.
	 */
	private function theRoute(WorkflowStepEnum $step): string
	{
		$lines = [];

		foreach (WorkflowStepEnum::cases() as $case) {
			$lines[] = $case->number() . '. ' . $case->title() . ($case === $step ? '  ← you are here' : '');
		}

		return "## The road a task travels\n\n" . implode("\n", $lines);
	}
}
