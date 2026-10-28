<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Objects\RuleFileEntityCollection;

/**
 * Reads the rule files of every project and compares them with mtvtd-core.
 *
 * @method static RuleFileEntityCollection execute()
 */
class ScanRulesAction extends Command
{
	public function handle(
		RuleFileSystemRepositoryContract $ruleFileSystemRepository,
		RuleFileRepositoryContract $ruleFileRepository,
	): RuleFileEntityCollection {
		$ruleFileObjects = $ruleFileSystemRepository->getAll(GetProjectsAction::execute());

		return $ruleFileRepository->replaceAll($ruleFileObjects);
	}
}
