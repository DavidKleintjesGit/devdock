<?php

declare(strict_types=1);

namespace Src\Project\Contracts;

use Src\Project\Objects\ProjectObjectCollection;

interface ProjectScanRepositoryContract
{
	/**
	 * Every folder under the given path that is a git checkout, with the
	 * standing of its working tree.
	 */
	public function getAll(string $sitesPath): ProjectObjectCollection;

	/**
	 * The branches of one checkout, so a task can be tied to one of them.
	 *
	 * @return list<string>
	 */
	public function getBranches(string $repositoryPath): array;
}
