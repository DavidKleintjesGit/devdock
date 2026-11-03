<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Access\Actions\GetAccessPolicyAction;
use Src\Access\Enums\AgentRoleEnum;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\CommitMessageObject;

/**
 * The commit message for what a round changed, written by the session that
 * made the changes. It knows what it did; the board only sees file names.
 *
 * @method static ?CommitMessageObject execute(string $card)
 */
class GenerateCommitMessageAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ClaudeProcessRepositoryContract $claudeProcessRepository,
	): ?CommitMessageObject {
		$promptRunEntity = $promptRunRepository->getLatestForCard($this->card);

		if ($promptRunEntity === null) {
			return null;
		}

		// a round from before the working copies has no folder written down
		$directory = $promptRunEntity->directory
			?? ($promptRunEntity->project === null
				? null
				: config('devdock.paths.sites') . '/' . $promptRunEntity->project);

		if ($directory === null) {
			return null;
		}

		// a round may already have written one at the end of its report
		$standing = ReadReportAction::execute($promptRunEntity->output)?->commitMessage;

		if ($standing !== null) {
			return $standing->withTicketKey($promptRunEntity->ticketKey);
		}

		$answer = $claudeProcessRepository->ask(
			GetAccessPolicyAction::execute(AgentRoleEnum::HELPER),
			$directory,
			$promptRunEntity->sessionUuid,
			$this->question($promptRunEntity->ticketKey),
		);

		return ReadReportAction::execute($answer)?->commitMessage?->withTicketKey($promptRunEntity->ticketKey);
	}

	/**
	 * The question keeps the session to writing, not to working: it only
	 * describes what it already changed.
	 */
	private function question(?string $ticketKey): string
	{
		$opening = $ticketKey === null
			? __('Begin de titel met wat er is veranderd.')
			: __('Begin de titel met :ticket en een spatie.', ['ticket' => $ticketKey]);

		return implode("\n", [
			__('Schrijf een commitbericht voor de wijzigingen die je in deze werkmap hebt gemaakt.'),
			__('Verander niets, draai geen tests en commit niet. Antwoord alleen met het blok hieronder.'),
			'',
			$opening,
			__('Schrijf in de taal van de laatste commits in deze repository; git log laat ze zien.'),
			__('De eerste regel is de titel: één zin, gebiedende wijs, maximaal 72 tekens.'),
			__('Daaronder een lege regel en vervolgens per wijziging een regel die begint met een streepje.'),
			'',
			'```' . 'commit',
			__('<titel>'),
			'',
			__('- <wat er is veranderd en waarom>'),
			'```',
		]);
	}
}
