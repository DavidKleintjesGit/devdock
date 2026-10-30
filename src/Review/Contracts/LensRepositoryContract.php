<?php

declare(strict_types=1);

namespace Src\Review\Contracts;

use Src\Review\Objects\LensEntityCollection;
use Src\Review\Objects\LensId;

interface LensRepositoryContract
{
	/**
	 * Every lens, with whether it is on for this project. Without a project
	 * the lenses come back as they stand by default.
	 */
	public function getAll(?string $project = null): LensEntityCollection;

	public function toggle(LensId $lensId, string $project, bool $isEnabled): void;
}
