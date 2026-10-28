<?php

declare(strict_types=1);

namespace Src\Skill\Actions;

use Src\Packages\Cqrs\Command;
use Src\Skill\Contracts\SkillFileSystemRepositoryContract;
use Src\Skill\Contracts\SkillRepositoryContract;
use Src\Skill\Objects\SkillId;

/**
 * The text of a SKILL.md, without the front matter: name and description are
 * already in the head of the panel.
 *
 * @method static ?string execute(SkillId $skillId)
 */
class GetSkillContentsAction extends Command
{
	public function __construct(
		public readonly SkillId $skillId,
	) {
	}

	public function handle(
		SkillRepositoryContract $skillRepository,
		SkillFileSystemRepositoryContract $skillFileSystemRepository,
	): ?string {
		$skillEntity = $skillRepository->getById($this->skillId);

		if ($skillEntity === null) {
			return null;
		}

		$contents = $skillFileSystemRepository->getContents($skillEntity);

		return $contents === null ? null : $this->withoutFrontMatter($contents);
	}

	private function withoutFrontMatter(string $contents): string
	{
		return (string) preg_replace('/^---\r?\n.*?\r?\n---\r?\n/s', '', $contents);
	}
}
