<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Objects\LensId;

/**
 * Puts a lens in or out of the rounds of one project. Other projects keep it
 * as it was.
 *
 * @method static void execute(LensId $lensId, string $project, bool $isEnabled)
 */
class ToggleLensAction extends Command
{
	public function __construct(
		public readonly LensId $lensId,
		public readonly string $project,
		public readonly bool $isEnabled,
	) {}

	public function handle(LensRepositoryContract $lensRepository): void
	{
		$lensRepository->toggle($this->lensId, $this->project, $this->isEnabled);
	}
}
