<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Contracts\ProjectPhpRepositoryContract;
use Src\Review\Enums\TestCommandEnum;
use Src\Review\Objects\LensEntity;
use Src\Review\Objects\TestCommandFactsObject;
use Src\Review\Policies\TestCommandPolicy;

/**
 * The command a lens runs in this folder, or null when it runs none.
 *
 * @method static ?string execute(LensEntity $lensEntity, string $directory)
 */
class GetLensCommandAction extends Command
{
	public function __construct(
		public readonly LensEntity $lensEntity,
		public readonly string $directory,
	) {}

	public function handle(ProjectPhpRepositoryContract $projectPhpRepository): ?string
	{
		$testCommand = resolve(TestCommandPolicy::class)->decide(new TestCommandFactsObject(
			lensCommand: $this->lensEntity->command,
			hasComposerTestScript: $projectPhpRepository->hasComposerScript($this->directory, 'test'),
		));

		return $testCommand === TestCommandEnum::COMPOSER_TEST ? 'composer test' : $this->lensEntity->command;
	}
}
