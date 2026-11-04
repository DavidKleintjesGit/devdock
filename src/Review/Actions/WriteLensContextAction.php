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
 * @method static string execute(string $worktreePath, LensEntity $lensEntity, array $files, ?string $previousReport, array $rules = [], ?string $base = null, array $newFiles = [])
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
		public readonly array $rules = [],
		public readonly ?string $base = null,
		public readonly array $newFiles = [],
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): string
	{
		$agentContextRepository->writeLensContext(
			$this->worktreePath,
			$this->lensEntity->name,
			ComposeLensContextAction::execute($this->lensEntity, $this->files, $this->previousReport, $this->rules, $this->base, $this->newFiles),
		);

		return self::assignmentFor($agentContextRepository->lensPath($this->lensEntity->name));
	}

	/**
	 * The one line a lens is sent, pointing it at its assignment.
	 */
	public static function assignmentFor(string $lensPath): string
	{
		return implode("\n", [
			__('Lees :file en doe wat daar staat.', ['file' => $lensPath]),
			__('Antwoord met precies het verdict-blok dat daarin beschreven staat en niets erbuiten.'),
		]);
	}
}
