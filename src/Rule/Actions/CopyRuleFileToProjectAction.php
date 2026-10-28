<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Objects\RuleFileId;

/**
 * Puts a rule file down on the same path in another project. When a file
 * already stands there, it goes under.
 *
 * @method static bool execute(RuleFileId $ruleFileId, string $project)
 */
class CopyRuleFileToProjectAction extends Command
{
	public function __construct(
		public readonly RuleFileId $ruleFileId,
		public readonly string $project,
	) {
	}

	public function handle(
		RuleFileRepositoryContract $ruleFileRepository,
		RuleFileSystemRepositoryContract $ruleFileSystemRepository,
	): bool {
		$ruleFileEntity = $ruleFileRepository->getById($this->ruleFileId);

		$projectEntity = GetProjectsAction::execute()
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)
			->first();

		if ($ruleFileEntity === null || $projectEntity === null) {
			return false;
		}

		if (! $ruleFileSystemRepository->copyToProject($ruleFileEntity, $projectEntity->path)) {
			return false;
		}

		ScanRulesAction::execute();

		return true;
	}
}
