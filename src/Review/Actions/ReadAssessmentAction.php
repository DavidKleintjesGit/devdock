<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\AssessmentVerdictEnum;
use Src\Review\Objects\AssessmentObject;
use Src\Review\Objects\ReviewFindingId;

/**
 * Lifts the assessment block out of what the agent wrote after a round: one
 * line per finding, its tag, the verdict and a proposal, split by a bar. A
 * line that does not read that way is left out.
 *
 * @method static list<AssessmentObject> execute(?string $text)
 */
class ReadAssessmentAction extends Command
{
	public const FENCE = 'assessment';

	public function __construct(
		public readonly ?string $text,
	) {}

	/**
	 * @return list<AssessmentObject>
	 */
	public function handle(): array
	{
		if (preg_match('/```' . self::FENCE . '\s*\n(.*?)```/s', (string) $this->text, $found) !== 1) {
			return [];
		}

		return collect(explode("\n", $found[1]))
			->map(fn (string $line): array => array_map('trim', explode('|', trim(ltrim(trim($line), '-*')), 3)))
			->filter(fn (array $parts): bool => count($parts) === 3
				&& preg_match('/^F(\d+)$/i', $parts[0]) === 1
				&& AssessmentVerdictEnum::tryFrom(mb_strtolower($parts[1])) !== null)
			->map(fn (array $parts): AssessmentObject => new AssessmentObject(
				reviewFindingId: new ReviewFindingId(id: (int) substr($parts[0], 1)),
				verdict: AssessmentVerdictEnum::from(mb_strtolower($parts[1])),
				proposal: $parts[2],
			))
			->values()
			->all();
	}
}
