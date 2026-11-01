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

	/**
	 * What this project changes about a lens of the standard. Null keeps the
	 * standard for that part; an empty command switches it off here.
	 *
	 * @param  array<int, string>|null  $paths
	 * @param  array<int, string>|null  $rules
	 */
	public function customize(LensId $lensId, string $project, ?array $paths, ?array $rules, ?string $command): void;

	/**
	 * A new lens: of the standard, or of one project when it is given.
	 *
	 * @param  array<int, string>  $paths
	 * @param  array<int, string>  $rules
	 */
	public function create(string $name, string $description, array $paths, array $rules, ?string $command, ?string $project): LensId;

	/**
	 * @param  array<int, string>  $paths
	 * @param  array<int, string>  $rules
	 */
	public function updateStandard(LensId $lensId, string $description, array $paths, array $rules, ?string $command): void;

	public function delete(LensId $lensId): void;
}
