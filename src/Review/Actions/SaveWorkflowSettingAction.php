<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\AfterRoundsEnum;
use Src\Review\Enums\ReviewModeEnum;
use Src\Review\Models\ProjectWorkflow;

/**
 * Writes down what a project sets for itself. A threshold left empty means
 * the standard holds again.
 *
 * @method static void execute(string $project, ?int $maxRounds, ?AfterRoundsEnum $afterRounds, ?string $context, ?ReviewModeEnum $reviewMode = null)
 */
class SaveWorkflowSettingAction extends Command
{
	public function __construct(
		public readonly string $project,
		public readonly ?int $maxRounds,
		public readonly ?AfterRoundsEnum $afterRounds,
		public readonly ?string $context,
		public readonly ?ReviewModeEnum $reviewMode = null,
	) {}

	public function handle(): void
	{
		ProjectWorkflow::query()->updateOrCreate(
			['project' => $this->project],
			[
				'max_rounds' => $this->maxRounds,
				'after_rounds' => $this->afterRounds?->value,
				'context' => blank($this->context) ? null : trim((string) $this->context),
				'review_mode' => $this->reviewMode?->value,
			],
		);
	}
}
