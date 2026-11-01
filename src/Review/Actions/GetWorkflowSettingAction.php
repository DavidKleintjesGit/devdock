<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\AfterRoundsEnum;
use Src\Review\Models\ProjectWorkflow;
use Src\Review\Objects\WorkflowSettingObject;

/**
 * The threshold and the addition to the way of working that hold in a
 * project, falling back on the standard.
 *
 * @method static WorkflowSettingObject execute(?string $project)
 */
class GetWorkflowSettingAction extends Command
{
	public function __construct(
		public readonly ?string $project,
	) {}

	public function handle(): WorkflowSettingObject
	{
		$projectWorkflow = $this->project === null
			? null
			: ProjectWorkflow::query()->where('project', $this->project)->first();

		$standardAfter = AfterRoundsEnum::tryFrom((string) config('devdock.review.after_rounds')) ?? AfterRoundsEnum::BACK_TO_YOU;

		return new WorkflowSettingObject(
			maxRounds: (int) ($projectWorkflow?->max_rounds ?? config('devdock.review.max_rounds')),
			afterRounds: AfterRoundsEnum::tryFrom((string) $projectWorkflow?->after_rounds) ?? $standardAfter,
			context: $projectWorkflow?->context,
			hasOwnThreshold: $projectWorkflow?->max_rounds !== null,
		);
	}
}
