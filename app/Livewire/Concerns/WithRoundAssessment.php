<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Board\Objects\CardObject;
use Src\Review\Actions\BuildFixPromptAction;
use Src\Review\Actions\BuildRoundPromptAction;
use Src\Review\Actions\GetRoundAssessmentAction;
use Src\Review\Actions\SetFindingStatusAction;
use Src\Review\Enums\FindingChoiceEnum;
use Src\Review\Objects\AssessedFindingObject;
use Src\Review\Objects\RoundAssessmentObject;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Your choice on a round that the agent assessed: per finding fix, ignore
 * or resolved. Every click writes the message to the agent again, which you
 * can still change before it goes.
 */
trait WithRoundAssessment
{
	/** @var array<int, string> the choice per finding id, a FindingChoiceEnum value */
	public array $findingChoices = [];

	/** @var array<int, string> why a finding stays as it is, per finding id */
	public array $ignoreReasons = [];

	/** The round the choices above belong to. */
	public ?int $assessedRound = null;

	public function chooseFinding(int $finding, string $choice): void
	{
		$this->findingChoices[$finding] = (FindingChoiceEnum::tryFrom($choice) ?? FindingChoiceEnum::FIX)->value;
		$this->writeFixPrompt();
	}

	public function updatedIgnoreReasons(): void
	{
		$this->writeFixPrompt();
	}

	/**
	 * Asks the agent to assess the findings of the last round, for a round that
	 * was not assessed yet, such as one from before you decided yourself.
	 */
	public function assessLatestRound(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$latestRound = $cardObject === null ? null : $this->latestRound($cardObject->id);
		$prompt = $latestRound === null ? null : BuildRoundPromptAction::execute($latestRound, true);

		if ($cardObject?->project === null || $cardObject->branch === null || $prompt === null) {
			$this->failure = __('Deze ronde heeft geen open bevindingen om te beoordelen.');

			return;
		}

		$this->failure = $this->startLinking(new WorkingCopyTaskObject(
			card: $cardObject->id,
			task: WorkingCopyTaskEnum::PROMPT,
			project: $cardObject->project,
			branch: $cardObject->branch,
			ticketKey: $cardObject->ticketKey,
			prompt: $prompt,
			reviewRoundId: $latestRound->reviewRoundId,
		));
	}

	/**
	 * Goes to the chat, where the message your choices make stands ready.
	 */
	public function toChatWithChoices(): void
	{
		$this->setDrawerTab('Agent');
	}

	/**
	 * The round of the card that waits on your choice. A round seen for the
	 * first time starts on what the agent's verdicts suggest, and its message
	 * is written into the chat.
	 */
	protected function roundAssessment(CardObject $cardObject): ?RoundAssessmentObject
	{
		$roundAssessmentObject = GetRoundAssessmentAction::execute($cardObject->id, $cardObject->project);

		if ($roundAssessmentObject === null || $this->assessedRound === $roundAssessmentObject->reviewRoundId->id) {
			return $roundAssessmentObject;
		}

		$this->assessedRound = $roundAssessmentObject->reviewRoundId->id;
		$this->findingChoices = [];
		$this->ignoreReasons = [];

		foreach ($roundAssessmentObject->findings as $assessedFindingObject) {
			$findingId = (int) $assessedFindingObject->finding->reviewFindingId->id;
			$this->findingChoices[$findingId] = $assessedFindingObject->suggestedChoice()->value;
			$this->ignoreReasons[$findingId] = $assessedFindingObject->assessment->proposal ?? '';
		}

		$this->prompt = BuildFixPromptAction::execute($roundAssessmentObject, $this->findingChoices, $this->ignoreReasons);

		return $roundAssessmentObject;
	}

	/**
	 * Writes down what you set aside and what is already done. Returns
	 * whether something is left for the agent to fix.
	 */
	protected function settleChoices(RoundAssessmentObject $roundAssessmentObject): bool
	{
		$hasFix = false;

		foreach ($roundAssessmentObject->findings as $assessedFindingObject) {
			$findingId = (int) $assessedFindingObject->finding->reviewFindingId->id;
			$choice = FindingChoiceEnum::tryFrom((string) ($this->findingChoices[$findingId] ?? '')) ?? $assessedFindingObject->suggestedChoice();
			$status = $choice->findingStatus();

			if ($status === null) {
				$hasFix = true;

				continue;
			}

			SetFindingStatusAction::execute($assessedFindingObject->finding->reviewFindingId, $status, $this->ignoreReasons[$findingId] ?? null);
		}

		return $hasFix;
	}

	/**
	 * The assessment of the open card that your choices belong to, if it still waits.
	 */
	protected function pendingAssessment(CardObject $cardObject): ?RoundAssessmentObject
	{
		$roundAssessmentObject = GetRoundAssessmentAction::execute($cardObject->id, $cardObject->project);

		return $roundAssessmentObject !== null && $roundAssessmentObject->reviewRoundId->id === $this->assessedRound ? $roundAssessmentObject : null;
	}

	private function writeFixPrompt(): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$roundAssessmentObject = $cardObject === null ? null : $this->pendingAssessment($cardObject);

		if ($roundAssessmentObject !== null) {
			$this->prompt = BuildFixPromptAction::execute($roundAssessmentObject, $this->findingChoices, $this->ignoreReasons);
		}
	}

	/**
	 * How many findings stand on each choice, in words, such as "2 fixen · 1 negeren".
	 */
	protected function choiceSummary(RoundAssessmentObject $roundAssessmentObject): string
	{
		$counts = collect($roundAssessmentObject->findings)
			->countBy(fn (AssessedFindingObject $assessedFindingObject): string => $this->findingChoices[(int) $assessedFindingObject->finding->reviewFindingId->id] ?? $assessedFindingObject->suggestedChoice()->value);

		return collect(FindingChoiceEnum::cases())
			->filter(fn (FindingChoiceEnum $findingChoiceEnum): bool => ($counts[$findingChoiceEnum->value] ?? 0) > 0)
			->map(fn (FindingChoiceEnum $findingChoiceEnum): string => $counts[$findingChoiceEnum->value] . ' ' . mb_strtolower($findingChoiceEnum->label()))
			->implode(' · ');
	}
}
