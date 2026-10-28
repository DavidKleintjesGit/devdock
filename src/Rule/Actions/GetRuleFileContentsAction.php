<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Objects\RuleFileId;

/**
 * The contents of a rule file, read from disk.
 *
 * @method static ?string execute(RuleFileId $ruleFileId)
 */
class GetRuleFileContentsAction extends Command
{
	public function __construct(
		public readonly RuleFileId $ruleFileId,
	) {
	}

	public function handle(
		RuleFileRepositoryContract $ruleFileRepository,
		RuleFileSystemRepositoryContract $ruleFileSystemRepository,
	): ?string {
		$ruleFileEntity = $ruleFileRepository->getById($this->ruleFileId);

		return $ruleFileEntity === null ? null : $ruleFileSystemRepository->getContents($ruleFileEntity);
	}
}
