<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Objects\RuleFileId;

/**
 * Pulls the version from mtvtd-core over a rule file. What was in it goes
 * under.
 *
 * @method static bool execute(RuleFileId $ruleFileId)
 */
class CopyRuleFileFromCoreAction extends Command
{
	public function __construct(
		public readonly RuleFileId $ruleFileId,
	) {
	}

	public function handle(
		RuleFileRepositoryContract $ruleFileRepository,
		RuleFileSystemRepositoryContract $ruleFileSystemRepository,
	): bool {
		$ruleFileEntity = $ruleFileRepository->getById($this->ruleFileId);

		if ($ruleFileEntity === null || ! $ruleFileSystemRepository->copyFromCore($ruleFileEntity)) {
			return false;
		}

		ScanRulesAction::execute();

		return true;
	}
}
