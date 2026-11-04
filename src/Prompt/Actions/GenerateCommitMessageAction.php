<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Access\Actions\GetRoleAccessAction;
use Src\Access\Enums\AgentRoleEnum;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Objects\CommitMessageObject;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DiffViewEnum;

/**
 * The commit message for exactly the files that are ticked to go along,
 * written from their changes as they stand now. A helper reads the patch; no
 * report of an earlier turn and no session of the agent comes into it.
 *
 * @method static ?CommitMessageObject execute(string $directory, array $files, ?string $ticketKey)
 */
class GenerateCommitMessageAction extends Command
{
	/**
	 * Enough patch to describe a commit by; past this the helper gets the file names of the rest.
	 */
	private const MAX_PATCH_CHARACTERS = 80_000;

	/**
	 * The length of a title the Working copy tab counts towards, ticket number included.
	 */
	private const TITLE_CHARACTERS = 72;

	/**
	 * @param  list<string>  $files  the files that go along in the commit
	 */
	public function __construct(
		public readonly string $directory,
		public readonly array $files,
		public readonly ?string $ticketKey,
	) {}

	public function handle(
		ClaudeProcessRepositoryContract $claudeProcessRepository,
		WorktreeRepositoryContract $worktreeRepository,
	): ?CommitMessageObject {
		if ($this->files === [] || ! is_dir($this->directory)) {
			return null;
		}

		$answer = $claudeProcessRepository->ask(
			GetRoleAccessAction::execute(AgentRoleEnum::HELPER),
			$this->directory,
			null,
			$this->question($this->patch($worktreeRepository)),
		);

		return ReadCommitMessageAction::execute($answer)?->withTicketKey($this->ticketKey);
	}

	/**
	 * The changes of the ticked files against the last commit, new files in full.
	 */
	private function patch(WorktreeRepositoryContract $worktreeRepository): string
	{
		$patch = '';
		$leftOut = [];

		foreach ($this->files as $file) {
			$filePatch = (string) $worktreeRepository->getDiff($this->directory, $file, DiffViewEnum::UNCOMMITTED);

			if (strlen($patch) + strlen($filePatch) > self::MAX_PATCH_CHARACTERS) {
				$leftOut[] = $file;

				continue;
			}

			$patch .= $filePatch . "\n";
		}

		return $leftOut === []
			? $patch
			: $patch . "\n" . __('Te groot om mee te sturen, alleen de namen: :files', ['files' => implode(', ', $leftOut)]);
	}

	private function question(string $patch): string
	{
		$opening = $this->ticketKey === null
			? __('Begin de titel met wat er is veranderd.')
			: __('Begin de titel met :ticket en een spatie.', ['ticket' => $this->ticketKey]);

		return implode("\n", [
			__('Schrijf een commitbericht voor precies de wijzigingen hieronder; dit is wat nu gecommit wordt.'),
			__('Beschrijf alleen wat in deze wijzigingen staat, niets van eerder werk of van wat je verder weet. Verander niets, draai niets en commit niet. Antwoord alleen met het blok onderaan.'),
			'',
			$opening,
			__('Schrijf in de taal van de laatste commits in deze repository; git log laat ze zien.'),
			__('De eerste regel is de titel: één zin, gebiedende wijs, over het belangrijkste dat verandert, en zonder het ticketnummer maximaal :max tekens.', ['max' => self::TITLE_CHARACTERS - mb_strlen((string) $this->ticketKey) - 1]),
			__('Daaronder een lege regel en vervolgens per wijziging een regel die begint met een streepje.'),
			'',
			__('Bestanden die meegaan: :files', ['files' => implode(', ', $this->files)]),
			'',
			'```diff',
			$patch,
			'```',
			'',
			'```' . 'commit',
			__('<titel>'),
			'',
			__('- <wat er is veranderd en waarom>'),
			'```',
		]);
	}
}
