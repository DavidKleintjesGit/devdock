<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Objects\LensEntity;

/**
 * Lays one lens's assignment down in the working copy and hands back the one
 * line that sends it there. The assignment is a file rather than a prompt, so
 * it can be read back afterwards exactly as the lens got it.
 *
 * @method static string execute(string $worktreePath, LensEntity $lensEntity, array $files, ?string $previousReport)
 */
class WriteLensContextAction extends Command
{
	/**
	 * @param  array<int, string>  $files
	 */
	public function __construct(
		public readonly string $worktreePath,
		public readonly LensEntity $lensEntity,
		public readonly array $files,
		public readonly ?string $previousReport = null,
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): string
	{
		$agentContextRepository->writeLensContext(
			$this->worktreePath,
			$this->lensEntity->name,
			ComposeLensContextAction::execute($this->lensEntity, $this->files, $this->previousReport),
		);

		return implode("\n", [
			__('Lees :file en doe wat daar staat.', [
				'file' => $agentContextRepository->lensPath($this->lensEntity->name),
			]),
			__('Antwoord met precies het verdict-blok dat daarin beschreven staat en niets erbuiten.'),
		]);
	}
}
