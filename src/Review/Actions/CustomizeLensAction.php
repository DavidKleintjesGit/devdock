<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Objects\LensId;

/**
 * Sets what a lens of the standard does differently in one project. Leaving
 * a part null keeps the standard for it.
 *
 * @method static void execute(LensId $lensId, string $project, ?array $paths, ?array $rules, ?string $command)
 */
class CustomizeLensAction extends Command
{
	/**
	 * @param  array<int, string>|null  $paths
	 * @param  array<int, string>|null  $rules
	 */
	public function __construct(
		public readonly LensId $lensId,
		public readonly string $project,
		public readonly ?array $paths,
		public readonly ?array $rules,
		public readonly ?string $command,
	) {}

	public function handle(LensRepositoryContract $lensRepository): void
	{
		$lensRepository->customize($this->lensId, $this->project, $this->paths, $this->rules, $this->command);
	}
}
