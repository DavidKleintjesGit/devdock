<?php

declare(strict_types=1);

namespace Src\Board\Policies;

use Src\Board\Enums\CardSubtabEnum;
use Src\Board\Enums\CardTabEnum;
use Src\Board\Enums\HintToneEnum;
use Src\Board\Objects\TabHintFactsObject;
use Src\Board\Objects\TabHintsDecisionObject;
use Src\Review\Enums\LoopStatusEnum;

/**
 * The rule for the hint dots of a card: a tab shows red where something
 * clashes or stands still, and amber where something waits on you. A main tab
 * gathers what its subtabs show.
 *
 * - Changes: amber for work that is not committed or not pushed.
 * - Branch: red for an open rebase or a conflict, amber for running behind.
 * - Setup: red for a working copy that is not ready.
 * - Review: red for a lens that fell over, amber when the loop hands it back
 *   to you or findings stay open while nobody works on them.
 * - Chat: amber for a question of the agent you have not read.
 * - Connections: red for changes asked on the pull request, amber for remarks
 *   you have not read.
 */
class TabHintPolicy
{
	private const BUSY_LOOP = [LoopStatusEnum::LENSES, LoopStatusEnum::AGENT, LoopStatusEnum::WAITING];

	public function decide(TabHintFactsObject $tabHintFactsObject): TabHintsDecisionObject
	{
		$subtabTones = $tabHintFactsObject->hasWorkingCopy ? [
			CardSubtabEnum::CHANGES->value => $this->tones(
				amber: $tabHintFactsObject->uncommittedFiles > 0 || $tabHintFactsObject->unpushedCommits > 0,
			),
			CardSubtabEnum::BRANCH->value => $this->tones(
				red: $tabHintFactsObject->isRebasing || $tabHintFactsObject->hasConflict,
				amber: $tabHintFactsObject->behind > 0,
			),
			CardSubtabEnum::SETUP->value => $this->tones(red: $tabHintFactsObject->isReady === false),
		] : [];

		$subtabTones[CardSubtabEnum::ROUNDS->value] = $this->tones(
			red: $tabHintFactsObject->loopStatus === LoopStatusEnum::LENS_FELL,
			amber: $tabHintFactsObject->loopStatus?->needsYou() === true
				|| ($tabHintFactsObject->openFindings > 0 && ! in_array($tabHintFactsObject->loopStatus, self::BUSY_LOOP, true)),
		);

		$tabTones = [
			CardTabEnum::CHAT->value => $this->tones(amber: $tabHintFactsObject->hasUnreadQuestion),
			CardTabEnum::WORKING_COPY->value => $this->gathered(
				$subtabTones[CardSubtabEnum::CHANGES->value] ?? [],
				$subtabTones[CardSubtabEnum::BRANCH->value] ?? [],
				$subtabTones[CardSubtabEnum::SETUP->value] ?? [],
			),
			CardTabEnum::REVIEW->value => $subtabTones[CardSubtabEnum::ROUNDS->value],
			CardTabEnum::LINKS->value => $this->tones(
				red: $tabHintFactsObject->hasChangesRequested,
				amber: $tabHintFactsObject->unreadRemarks > 0,
			),
		];

		return new TabHintsDecisionObject(tabTones: $tabTones, subtabTones: $subtabTones);
	}

	/**
	 * @return list<HintToneEnum>
	 */
	private function tones(bool $red = false, bool $amber = false): array
	{
		return array_values(array_filter([
			$red ? HintToneEnum::RED : null,
			$amber ? HintToneEnum::AMBER : null,
		]));
	}

	/**
	 * @param  list<HintToneEnum>  ...$toneLists
	 * @return list<HintToneEnum>
	 */
	private function gathered(array ...$toneLists): array
	{
		$tones = array_merge(...$toneLists);

		return array_values(array_filter(HintToneEnum::cases(), fn (HintToneEnum $hintTone): bool => in_array($hintTone, $tones, true)));
	}
}
