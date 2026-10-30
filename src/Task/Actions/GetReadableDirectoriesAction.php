<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;

/**
 * The folders outside its own working copy that this task's agent may open.
 * Nothing is allowed by itself: every project here was put there by hand, so
 * an agent never wanders into a repository nobody invited it to.
 *
 * @method static array<int, string> execute(string $card)
 */
class GetReadableDirectoriesAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	/**
	 * @return array<int, string>
	 */
	public function handle(TaskRepositoryContract $taskRepository): array
	{
		$names = $taskRepository->getByKey($this->card)?->valuesFor(TaskLinkKindEnum::READABLE_PROJECT) ?? [];

		if ($names === []) {
			return [];
		}

		$directories = [];

		foreach (GetProjectsAction::execute()->toArray() as $projectEntity) {
			if (in_array($projectEntity->name, $names, true)) {
				$directories[] = $projectEntity->path;
			}
		}

		return $directories;
	}
}
