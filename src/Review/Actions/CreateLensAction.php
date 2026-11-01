<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Objects\LensId;

/**
 * Adds a lens to one project, or to the standard. A project lens can be put
 * in the standard as well: what it watches for goes along, its paths and rule
 * files of this project do not.
 *
 * @method static LensId execute(string $name, string $description, array $paths, array $rules, ?string $command, ?string $project, bool $alsoInStandard = false)
 */
class CreateLensAction extends Command
{
	/**
	 * @param  array<int, string>  $paths
	 * @param  array<int, string>  $rules
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly array $paths,
		public readonly array $rules,
		public readonly ?string $command,
		public readonly ?string $project,
		public readonly bool $alsoInStandard = false,
	) {}

	public function handle(LensRepositoryContract $lensRepository): LensId
	{
		if ($this->project !== null && $this->alsoInStandard) {
			$lensRepository->create($this->name, $this->description, [], [], null, null);
		}

		return $lensRepository->create($this->name, $this->description, $this->paths, $this->rules, $this->command, $this->project);
	}
}
