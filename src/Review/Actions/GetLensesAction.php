<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Objects\LensEntityCollection;

/**
 * Every lens, with whether it joins the rounds of this project.
 *
 * @method static LensEntityCollection execute(?string $project)
 */
class GetLensesAction extends Command
{
	public function __construct(
		public readonly ?string $project = null,
	) {}

	public function handle(LensRepositoryContract $lensRepository): LensEntityCollection
	{
		return $lensRepository->getAll($this->project);
	}
}
