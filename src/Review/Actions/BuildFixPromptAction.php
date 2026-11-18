<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Support\Collection;
use Src\Packages\Cqrs\Command;
use Src\Review\Enums\FindingChoiceEnum;
use Src\Review\Objects\AssessedFindingObject;
use Src\Review\Objects\RoundAssessmentObject;

/**
 * The message to the agent that your choices on a round add up to: what it
 * fixes, with its own proposal, what stays as it is and why, and what is
 * already done. You can still change the text before it goes.
 *
 * @method static string execute(RoundAssessmentObject $roundAssessmentObject, array $choices, array $reasons)
 */
class BuildFixPromptAction extends Command
{
	/**
	 * @param  array<int|string, string>  $choices  the choice per finding id
	 * @param  array<int|string, string>  $reasons  why a finding stays as it is, per finding id
	 */
	public function __construct(
		public readonly RoundAssessmentObject $roundAssessmentObject,
		public readonly array $choices,
		public readonly array $reasons,
	) {}

	public function handle(): string
	{
		$byChoice = collect($this->roundAssessmentObject->findings)
			->groupBy(fn (AssessedFindingObject $assessedFindingObject): string => $this->choiceFor($assessedFindingObject)->value);

		$toFix = $byChoice->get(FindingChoiceEnum::FIX->value, collect());

		return implode("\n\n", array_filter([
			$toFix->isEmpty() ? null : __('Fix deze punten uit ronde :number, alleen deze:', ['number' => $this->roundAssessmentObject->roundNumber]) . "\n" . $toFix
				->values()
				->map(fn (AssessedFindingObject $assessedFindingObject, int $index): string => ($index + 1) . '. ' . $assessedFindingObject->finding->tag() . ' ' . $assessedFindingObject->finding->lensName . ' · ' . $assessedFindingObject->finding->line()
					. ($assessedFindingObject->assessment === null ? '' : "\n   " . __('Jouw voorstel: :proposal', ['proposal' => $assessedFindingObject->assessment->proposal])))
				->implode("\n"),
			$this->listed($byChoice->get(FindingChoiceEnum::IGNORE->value, collect()), __('Laat liggen:'), true),
			$this->listed($byChoice->get(FindingChoiceEnum::RESOLVED->value, collect()), __('Al opgelost:'), false),
			$toFix->isEmpty() ? null : __('Sluit af met status done; daarna start DevDock één nieuwe ronde van de lenzen.'),
		]));
	}

	private function choiceFor(AssessedFindingObject $assessedFindingObject): FindingChoiceEnum
	{
		return FindingChoiceEnum::tryFrom((string) ($this->choices[(int) $assessedFindingObject->finding->reviewFindingId->id] ?? ''))
			?? $assessedFindingObject->suggestedChoice();
	}

	/**
	 * @param  Collection<int, AssessedFindingObject>  $assessedFindingObjects
	 */
	private function listed(Collection $assessedFindingObjects, string $heading, bool $withReason): ?string
	{
		if ($assessedFindingObjects->isEmpty()) {
			return null;
		}

		return $heading . "\n" . $assessedFindingObjects
			->map(fn (AssessedFindingObject $assessedFindingObject): string => '- ' . $assessedFindingObject->finding->tag() . ' ' . ($assessedFindingObject->finding->location ?? $assessedFindingObject->finding->lensName)
				. ($withReason && filled($this->reasons[(int) $assessedFindingObject->finding->reviewFindingId->id] ?? null) ? ' — ' . trim((string) $this->reasons[(int) $assessedFindingObject->finding->reviewFindingId->id]) : ''))
			->implode("\n");
	}
}
