<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Objects\RuleFileEntityCollection;

/**
 * @method static RuleFileEntityCollection execute()
 */
class GetRuleFilesAction extends Command
{
	public function handle(RuleFileRepositoryContract $ruleFileRepository): RuleFileEntityCollection
	{
		return $ruleFileRepository->getAll();
	}
}
