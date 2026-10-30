<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;

/**
 * A name for the branch a task will work on, proposed by a session that reads
 * the ticket and sees how this project already names its branches. A task
 * with a ticket number needs none of that, so this is for the rest.
 *
 * @method static ?string execute(string $card)
 */
class GenerateBranchNameAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	public function handle(ClaudeProcessRepositoryContract $claudeProcessRepository): ?string
	{
		$cardObject = collect(BuildBoardAction::execute())
			->first(fn (CardObject $cardObject) => $cardObject->id === $this->card);

		if ($cardObject?->project === null) {
			return null;
		}

		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $cardObject->project);

		if ($projectEntity === null) {
			return null;
		}

		// the checkout itself: there is no working copy yet, and the branches
		// it already holds are the example to follow
		$answer = $claudeProcessRepository->ask($projectEntity->path, null, $this->question($cardObject));

		return $this->readName($answer);
	}

	private function question(CardObject $cardObject): string
	{
		$lines = [
			__('Stel een branchnaam voor voor de taak hieronder.'),
			__('Verander niets en maak geen branch aan. Kijk met git branch -a hoe dit project zijn branches noemt en volg dat.'),
			__('Antwoord met één regel: alleen de naam, zonder uitleg en zonder backticks.'),
			'',
			__('Titel: :title', ['title' => $cardObject->title]),
		];

		if ($cardObject->ticketKey !== null) {
			$lines[] = __('Ticket: :ticket', ['ticket' => $cardObject->ticketKey]);
		}

		if (filled($cardObject->description)) {
			$lines[] = __('Beschrijving: :description', ['description' => $cardObject->description]);
		}

		return implode("\n", $lines);
	}

	/**
	 * The one name in the answer. A session that explains itself anyway puts
	 * the name on a line of its own, so the first line that reads like a
	 * branch is the one.
	 */
	private function readName(?string $answer): ?string
	{
		foreach (preg_split('/\R/', (string) $answer) ?: [] as $line) {
			$name = trim(trim($line), '`\'" ');

			if ($name !== '' && preg_match('#^[A-Za-z0-9][A-Za-z0-9/._-]{2,80}$#', $name) === 1) {
				return $name;
			}
		}

		return null;
	}
}
