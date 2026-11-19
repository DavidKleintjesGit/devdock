<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Prompt\Actions\SendPromptAction;
use Src\Board\Objects\CardObject;
use Src\Review\Actions\BuildFixPromptAction;
use Src\Review\Actions\BuildRoundPromptAction;
use Src\Review\Actions\GetRoundAssessmentAction;
use Src\Review\Actions\GetRoundChoicesAction;
use Src\Review\Actions\KeepRoundChoicesAction;
use Src\Review\Actions\SetFindingStatusAction;
use Src\Review\Enums\FindingChoiceEnum;
use Src\Review\Objects\AssessedFindingObject;
use Src\Review\Objects\ReviewRoundId;
use Src\Review\Objects\RoundAssessmentObject;
use Src\Review\Objects\RoundChoicesObject;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Your choice on a round that the agent assessed: per finding fix, ignore
 * or resolved. The choices add up to a message of their own, next to the
 * findings, which you can change before you hand it to the agent. The chat
 * stays yours.
 */
trait WithRoundAssessment
{
	/** @var array<int, string> the choice per finding id, a FindingChoiceEnum value */
	public array $findingChoices = [];

	/** @var array<int, string> why a finding stays as it is, per finding id */
	public array $ignoreReasons = [];

	/** The round the choices above belong to. */
	public ?int $assessedRound = null;

	/** The message to the agent your choices add up to. */
	public string $fixMessage = '';

	/** Whether you wrote the message yourself; then your clicks leave it alone. */
	public bool $isFixMessageOwn = false;

	public function chooseFinding(int $finding, string $choice): void
	{
		$this->findingChoices[$finding] = (FindingChoiceEnum::tryFrom($choice) ?? FindingChoiceEnum::FIX)->value;
		$this->keepChoices();
	}

	public function updatedIgnoreReasons(): void
	{
		$this->keepChoices();
	}

	public function updatedFixMessage(): void
	{
		$this->isFixMessageOwn = true;
		$this->keepChoices();
	}

	/**
	 * Lets the message follow your choices again.
	 */
	public function rebuildFixMessage(): void
	{
		$this->isFixMessageOwn = false;
		$this->keepChoices();
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

		SendPromptAction::execute(new WorkingCopyTaskObject(
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
	 * Settles your choices and hands what is left to fix to the agent, with the
	 * message next to the findings. With nothing left to fix the round closes
	 * without a turn of the agent.
	 */
	public function handFindingsToAgent(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$roundAssessmentObject = $cardObject === null ? null : $this->pendingAssessment($cardObject);

		if ($cardObject?->project === null || $cardObject->branch === null || $roundAssessmentObject === null) {
			$this->failure = __('Deze ronde wacht niet meer op je keuze.');

			return;
		}

		$hasFix = $this->settleChoices($roundAssessmentObject);
		KeepRoundChoicesAction::execute($roundAssessmentObject->reviewRoundId, null);
		$this->assessedRound = null;

		if (! $hasFix) {
			$this->committed = __('Alles beoordeeld; er start geen nieuwe ronde.');

			return;
		}

		SendPromptAction::execute(new WorkingCopyTaskObject(
			card: $cardObject->id,
			task: WorkingCopyTaskEnum::PROMPT,
			project: $cardObject->project,
			branch: $cardObject->branch,
			ticketKey: $cardObject->ticketKey,
			prompt: $this->fixMessage,
			reviewRoundId: $roundAssessmentObject->reviewRoundId,
		));
	}

	/**
	 * The round of the card that waits on your choice, with the choices you
	 * kept for it. A round without kept choices starts on what the agent's
	 * verdicts suggest.
	 */
	protected function roundAssessment(CardObject $cardObject): ?RoundAssessmentObject
	{
		$roundAssessmentObject = GetRoundAssessmentAction::execute($cardObject->id, $cardObject->project);

		if ($roundAssessmentObject === null || $this->assessedRound === $roundAssessmentObject->reviewRoundId->id) {
			return $roundAssessmentObject;
		}

		$this->assessedRound = $roundAssessmentObject->reviewRoundId->id;
		$roundChoicesObject = GetRoundChoicesAction::execute($roundAssessmentObject->reviewRoundId);
		$this->findingChoices = [];
		$this->ignoreReasons = [];

		foreach ($roundAssessmentObject->findings as $assessedFindingObject) {
			$findingId = (int) $assessedFindingObject->finding->reviewFindingId->id;
			$this->findingChoices[$findingId] = $roundChoicesObject?->choices[$findingId] ?? $assessedFindingObject->suggestedChoice()->value;
			$this->ignoreReasons[$findingId] = $roundChoicesObject?->reasons[$findingId] ?? $assessedFindingObject->assessment->proposal ?? '';
		}

		$this->isFixMessageOwn = $roundChoicesObject?->message !== null;
		$this->fixMessage = $roundChoicesObject?->message ?? BuildFixPromptAction::execute($roundAssessmentObject, $this->findingChoices, $this->ignoreReasons);

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

	/**
	 * Keeps your choices with the round, and has the message follow them unless you wrote it yourself.
	 */
	private function keepChoices(): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$roundAssessmentObject = $cardObject === null ? null : $this->pendingAssessment($cardObject);

		if ($roundAssessmentObject === null) {
			return;
		}

		if (! $this->isFixMessageOwn) {
			$this->fixMessage = BuildFixPromptAction::execute($roundAssessmentObject, $this->findingChoices, $this->ignoreReasons);
		}

		KeepRoundChoicesAction::execute(new ReviewRoundId(id: $this->assessedRound), new RoundChoicesObject(
			choices: $this->findingChoices,
			reasons: $this->ignoreReasons,
			message: $this->isFixMessageOwn ? $this->fixMessage : null,
		));
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
