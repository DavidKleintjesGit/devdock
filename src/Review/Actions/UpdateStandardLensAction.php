<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Objects\LensId;

/**
 * Changes a lens of the standard. Every project that keeps the standard for
 * that lens gets it at its next round.
 *
 * @method static void execute(LensId $lensId, string $description, array $paths, array $rules, ?string $command)
 */
class UpdateStandardLensAction extends Command
{
	/**
	 * @param  array<int, string>  $paths
	 * @param  array<int, string>  $rules
	 */
	public function __construct(
		public readonly LensId $lensId,
		public readonly string $description,
		public readonly array $paths,
		public readonly array $rules,
		public readonly ?string $command,
	) {}

	public function handle(LensRepositoryContract $lensRepository): void
	{
		$lensRepository->updateStandard($this->lensId, $this->description, $this->paths, $this->rules, $this->command);
	}
}
