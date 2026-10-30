<?php

declare(strict_types=1);

namespace Src\Rule\Repositories;

use Src\Project\Actions\GetProjectsAction;
use Src\Rule\Contracts\PermissionRepositoryContract;
use Src\Rule\Objects\PermissionRuleObject;
use Src\Rule\Objects\PermissionRuleObjectCollection;

class PermissionRepository implements PermissionRepositoryContract
{
	/**
	 * Waar Claude Code zijn toestemmingen vandaan haalt, in de volgorde waarin
	 * hij ze leest: eerst die van jou, dan die van het project, dan je eigen
	 * uitzonderingen daarop. Ze vervangen elkaar niet maar stapelen.
	 */
	private const USER_PATH = '/.claude/settings.json';

	private const PROJECT_PATHS = [
		'.claude/settings.json',
		'.claude/settings.local.json',
	];

	private const KINDS = ['allow', 'deny', 'ask'];

	public function getAll(): PermissionRuleObjectCollection
	{
		$rules = $this->read($this->homePath() . self::USER_PATH, '~' . self::USER_PATH, null, true);

		foreach (GetProjectsAction::execute() as $projectEntity) {
			$counts = $this->isTrusted($projectEntity->path);

			foreach (self::PROJECT_PATHS as $relative) {
				$rules = [...$rules, ...$this->read(
					rtrim(strtr($projectEntity->path, DIRECTORY_SEPARATOR, '/'), '/') . '/' . $relative,
					$relative,
					$projectEntity->name,
					$counts,
				)];
			}
		}

		return PermissionRuleObjectCollection::fromArray($rules);
	}

	/**
	 * @return array<int, PermissionRuleObject>
	 */
	private function read(string $file, string $source, ?string $project, bool $counts): array
	{
		if (! is_file($file)) {
			return [];
		}

		$settings = json_decode((string) file_get_contents($file), true);

		if (! is_array($settings)) {
			return [];
		}

		$rules = [];

		foreach (self::KINDS as $kind) {
			foreach ($settings['permissions'][$kind] ?? [] as $pattern) {
				$rules[] = new PermissionRuleObject(
					kind: $kind,
					pattern: (string) $pattern,
					source: $source,
					project: $project,
					counts: $counts,
				);
			}
		}

		return $rules;
	}

	/**
	 * Claude Code negeert de regels van een map die je niet vertrouwd hebt, en
	 * houdt die stand bij op het pad precies zoals het geschreven staat.
	 */
	private function isTrusted(string $path): bool
	{
		$file = $this->homePath() . '/.claude.json';

		if (! is_file($file)) {
			return true;
		}

		$settings = json_decode((string) file_get_contents($file), true);
		$projects = is_array($settings) ? ($settings['projects'] ?? []) : [];

		// Claude Code zoekt op het pad precies zoals hij het zelf schrijft, met
		// schuine strepen. Een map die onder de backslash-variant vertrouwd is
		// telt dus niet mee, en dat is geen theorie: daar liep een agent op vast
		$wanted = rtrim(strtr($path, DIRECTORY_SEPARATOR, '/'), '/');

		return ($projects[$wanted]['hasTrustDialogAccepted'] ?? false) === true;
	}

	private function homePath(): string
	{
		$home = (string) (getenv('USERPROFILE') ?: getenv('HOME'));

		return rtrim(strtr($home, DIRECTORY_SEPARATOR, '/'), '/');
	}
}
