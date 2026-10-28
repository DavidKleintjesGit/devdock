<?php

declare(strict_types=1);

namespace Src\Skill\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Skill\Enums\SkillScopeEnum;

/**
 * A skill Claude Code can pick up, globally, in a project or from a plugin.
 */
class SkillEntity extends ValueObject
{
	public function __construct(
		public readonly SkillId $skillId,
		public readonly string $name,
		public readonly SkillScopeEnum $scope,
		public readonly ?string $project,
		public readonly ?string $plugin,
		public readonly ?string $description,
		public readonly string $path,
		public readonly int $lines,
		public readonly ?CarbonImmutable $scannedAt,
	) {
		parent::__construct();
	}

	public function isEditable(): bool
	{
		return $this->scope->isEditable();
	}

	/**
	 * Where the skill comes from, as it stands on the row.
	 */
	public function location(): string
	{
		return match ($this->scope) {
			SkillScopeEnum::GLOBALLY => '~/.claude/skills/' . $this->name . '/SKILL.md',
			SkillScopeEnum::PROJECT => $this->project . '/.claude/skills/' . $this->name . '/SKILL.md',
			SkillScopeEnum::PLUGIN => __('plugin :name', ['name' => $this->plugin]),
		};
	}

	public function placeLabel(): string
	{
		return match ($this->scope) {
			SkillScopeEnum::GLOBALLY => __('Globaal, geldt in elk project'),
			SkillScopeEnum::PLUGIN => __('Uit een plugin'),
			SkillScopeEnum::PROJECT => $this->project ?? __('Van een project'),
		};
	}

	/**
	 * The folder name of the skill; that decides where a copy lands.
	 */
	public function directoryName(): string
	{
		return basename(dirname($this->path));
	}

	public function matches(string $term): bool
	{
		$haystack = strtolower(implode(' ', [$this->name, $this->description, $this->project, $this->plugin]));

		return str_contains($haystack, strtolower($term));
	}
}
