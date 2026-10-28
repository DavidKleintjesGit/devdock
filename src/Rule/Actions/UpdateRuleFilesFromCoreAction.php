<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Objects\RuleFileEntity;
use Src\Rule\Objects\RuleFileEntityCollection;

/**
 * Pulls core over every behind rule file in the given projects. What was in
 * them goes under.
 *
 * @method static RuleFileEntityCollection execute(array $projects)
 */
class UpdateRuleFilesFromCoreAction extends Command
{
	/**
	 * @param  array<int, string>  $projects  leeg betekent elk project
	 */
	public function __construct(
		public readonly array $projects,
	) {
	}

	public function handle(
		RuleFileRepositoryContract $ruleFileRepository,
		RuleFileSystemRepositoryContract $ruleFileSystemRepository,
	): RuleFileEntityCollection {
		$behind = collect($ruleFileRepository->getAll())
			->filter(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->isBehindCore())
			->filter(fn (RuleFileEntity $ruleFileEntity) => $this->projects === []
				|| in_array($ruleFileEntity->project, $this->projects, true));

		$updated = $behind
			->filter(fn (RuleFileEntity $ruleFileEntity) => $ruleFileSystemRepository->copyFromCore($ruleFileEntity))
			->values()
			->all();

		if ($updated !== []) {
			ScanRulesAction::execute();
		}

		return RuleFileEntityCollection::fromArray($updated);
	}
}
