<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\ContextAudienceEnum;
use Src\Review\Enums\ContextOriginEnum;
use Src\Review\Objects\ContextFileObject;

/**
 * Lays the context of an agent or a lens out by origin, from what steers the
 * answer most to what least, with an estimate of how many tokens each source
 * and each group takes. A skill counts with its description only, since that
 * is all Claude reads of it until the skill is used.
 *
 * @method static array execute(array $entries, ?string $projectPath, ?string $workingPath)
 */
class BuildContextGroupsAction extends Command
{
	/**
	 * Characters per token, the usual rough measure for English and code.
	 */
	private const CHARACTERS_PER_TOKEN = 4;

	/**
	 * @param  list<array{file: ContextFileObject, audience: ContextAudienceEnum, own: bool}>  $entries
	 */
	public function __construct(
		public readonly array $entries,
		public readonly ?string $projectPath,
		public readonly ?string $workingPath,
	) {}

	/**
	 * @return array{groups: list<array<string, mixed>>, tokens: int, tokensLabel: string, share: list<array<string, mixed>>}
	 */
	public function handle(): array
	{
		$home = str_replace('\\', '/', (string) (getenv('USERPROFILE') ?: getenv('HOME'))) . '/.claude/';
		$byOrigin = [];

		foreach ($this->entries as $entry) {
			$origin = ContextOriginEnum::of($entry['file'], $home);
			$tokens = $this->tokensOf($entry['file']);

			$byOrigin[$origin->value][] = [
				'file' => $entry['file'],
				'audience' => $entry['audience'],
				'own' => $entry['own'],
				'name' => $this->displayName($entry['file'], $home),
				'tokens' => $tokens,
				'tokensLabel' => $this->tokensLabel($tokens),
			];
		}

		$groups = collect(ContextOriginEnum::cases())
			->filter(fn (ContextOriginEnum $origin): bool => isset($byOrigin[$origin->value]))
			->map(function (ContextOriginEnum $origin) use ($byOrigin): array {
				$tokens = array_sum(array_column($byOrigin[$origin->value], 'tokens'));

				return [
					'origin' => $origin,
					'files' => $byOrigin[$origin->value],
					'count' => count($byOrigin[$origin->value]),
					'tokens' => $tokens,
					'tokensLabel' => $this->tokensLabel($tokens),
				];
			})
			->values();

		$total = (int) $groups->sum('tokens');

		return [
			'groups' => $groups->all(),
			'tokens' => $total,
			'tokensLabel' => $this->tokensLabel($total),
			'share' => $groups
				->filter(fn (array $group): bool => $group['tokens'] > 0)
				->map(fn (array $group): array => [
					'origin' => $group['origin'],
					'percent' => $total === 0 ? 0 : max(1, (int) round($group['tokens'] / $total * 100)),
				])
				->values()
				->all(),
		];
	}

	private function tokensOf(ContextFileObject $contextFileObject): int
	{
		$text = str_starts_with($contextFileObject->key, 'skill:')
			? (string) $contextFileObject->description
			: (string) $contextFileObject->contents;

		return (int) ceil(mb_strlen($text) / self::CHARACTERS_PER_TOKEN);
	}

	/**
	 * A count of tokens the way the list shows it: 320, or 1,2k from a thousand on.
	 */
	private function tokensLabel(int $tokens): string
	{
		return $tokens < 1000 ? (string) $tokens : number_format($tokens / 1000, 1, ',', '.') . 'k';
	}

	/**
	 * The path of a file inside the folder it comes from, or its own name when it is no file.
	 */
	private function displayName(ContextFileObject $contextFileObject, string $home): string
	{
		if ($contextFileObject->path === null || str_starts_with($contextFileObject->key, 'skill:')) {
			return $contextFileObject->name;
		}

		$path = str_replace('\\', '/', $contextFileObject->path);

		foreach ([$this->workingPath, $this->projectPath, rtrim($home, '/'), '~/.claude'] as $root) {
			$root = $root === null ? null : rtrim(str_replace('\\', '/', $root), '/') . '/';

			if ($root !== null && str_starts_with(strtolower($path), strtolower($root))) {
				return substr($path, strlen($root));
			}
		}

		return $path;
	}
}
