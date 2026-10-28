<?php

declare(strict_types=1);

namespace Src\Rule\Contracts;

use Src\Project\Objects\ProjectEntityCollection;
use Src\Rule\Objects\RuleFileEntity;
use Src\Rule\Objects\RuleFileObjectCollection;

interface RuleFileSystemRepositoryContract
{
	/**
	 * Every rule file in the given projects, with the group it belongs to and
	 * how it stands against its counterpart in mtvtd-core.
	 */
	public function getAll(ProjectEntityCollection $projectEntities): RuleFileObjectCollection;

	public function getContents(RuleFileEntity $ruleFileEntity): ?string;

	/**
	 * Pulls the version from mtvtd-core over the file.
	 */
	public function copyFromCore(RuleFileEntity $ruleFileEntity): bool;

	/**
	 * Puts the rule file down on the same path in another project.
	 */
	public function copyToProject(RuleFileEntity $ruleFileEntity, string $projectPath): bool;
}
