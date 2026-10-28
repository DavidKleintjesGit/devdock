<?php

declare(strict_types=1);

namespace Src\Project\Contracts;

use Src\Project\Objects\ProjectEntity;
use Src\Project\Objects\ProjectEntityCollection;
use Src\Project\Objects\ProjectObject;
use Src\Project\Objects\ProjectObjectCollection;

interface ProjectRepositoryContract
{
	public function create(ProjectObject $projectObject): ProjectEntity;

	public function getAll(): ProjectEntityCollection;

	public function getByName(string $name): ?ProjectEntity;

	/**
	 * Brings the stored projects in line with what the scan found: what is added
	 * gets created, what already stands gets updated, and what is no longer on
	 * disk disappears.
	 */
	public function replaceAll(ProjectObjectCollection $projectObjects): ProjectEntityCollection;
}
