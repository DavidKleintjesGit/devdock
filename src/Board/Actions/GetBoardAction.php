<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Src\Board\Objects\CardObjectCollection;
use Src\Packages\Cqrs\Command;
use Src\Packages\Process\DetachedCommand;

/**
 * The board as BuildBoardAction makes it, kept until a row under it changes.
 * One click asks for the board in the panel, the board and the layout; this
 * builds it once for all of them.
 *
 * What you change with a click shows at once: the board builds again in the
 * request. What changes while agents, lenses and fetch rounds work is taken
 * in on the side: the page gets the kept board straight away, and a process of
 * its own builds the new one. Fresh builds it now, as a fetch round does.
 *
 * @method static CardObjectCollection execute(bool $isFresh = false)
 */
class GetBoardAction extends Command
{
	public const KEY = 'board.cards';

	private const BUILDING_KEY = 'board.building';

	/**
	 * How long one build on the side may take before another may start.
	 */
	private const BUILDING_SECONDS = 60;

	/**
	 * The tables a click of yours changes, with the column that says when a
	 * row last changed. A change in any of them makes the board build at once.
	 */
	private const CLICK_TABLES = [
		'card_views' => 'updated_at',
		'project_workflows' => 'updated_at',
		'task_agents' => 'updated_at',
		'task_links' => 'updated_at',
		'tasks' => 'updated_at',
		'todos' => 'updated_at',
	];

	/**
	 * The tables that change while work runs by itself. A change in them
	 * builds the board again on the side.
	 */
	private const WORK_TABLES = [
		'claude_sessions' => 'updated_at',
		// only a finished round counts: it brings the git state the cards show, while a running one writes its progress on every source
		'fetch_runs' => 'finished_at',
		'projects' => 'updated_at',
		'prompt_runs' => 'updated_at',
		'pull_requests' => 'updated_at',
		'review_results' => 'updated_at',
		'review_rounds' => 'updated_at',
		'tickets' => 'updated_at',
		'worktrees' => 'updated_at',
	];

	public function __construct(
		public readonly bool $isFresh = false,
	) {}

	/**
	 * The board stores as plain text, since the cache refuses objects; reading
	 * it back allows only the classes a board is made of.
	 */
	private const ALLOWED_NAMESPACES = ['Src\\', 'Carbon\\'];

	public function handle(DetachedCommand $detachedCommand): CardObjectCollection
	{
		$clickVersion = $this->version(self::CLICK_TABLES);
		$workVersion = $this->version(self::WORK_TABLES);
		$kept = $this->isFresh ? null : Cache::get(self::KEY);
		$cardObjectCollection = is_array($kept) && ($kept['click_version'] ?? null) === $clickVersion
			? $this->unpack((string) $kept['board'])
			: null;

		$isCurrent = ($kept['work_version'] ?? null) === $workVersion;

		// a test has no side to build on, so it builds in place
		if ($cardObjectCollection !== null && ($isCurrent || ! app()->runningUnitTests())) {
			if (! $isCurrent && Cache::add(self::BUILDING_KEY, true, self::BUILDING_SECONDS)) {
				$detachedCommand->artisan('devdock:board');
			}

			return $cardObjectCollection;
		}

		$cardObjectCollection = BuildBoardAction::execute();

		Cache::forever(self::KEY, [
			'click_version' => $clickVersion,
			'work_version' => $workVersion,
			'built_at' => microtime(true),
			'board' => serialize($cardObjectCollection),
		]);
		Cache::forget(self::BUILDING_KEY);

		return $cardObjectCollection;
	}

	/**
	 * When the kept board was built, so an open page knows it has a newer one to show.
	 */
	public static function builtAt(): float
	{
		return (float) (Cache::get(self::KEY)['built_at'] ?? 0);
	}

	/**
	 * How many rows each table holds and when one last changed, in one query:
	 * it runs on every click, so one round trip instead of thirty. Rows whose
	 * column is still empty, such as a round that has not finished, are not counted.
	 *
	 * @param  array<string, string>  $tables
	 */
	private function version(array $tables): string
	{
		$parts = array_map(
			fn (string $table, string $column): Builder => DB::table($table)->selectRaw("? as name, count({$column}) as row_count, max({$column}) as changed_at", [$table]),
			array_keys($tables),
			$tables,
		);
		$query = array_shift($parts);

		foreach ($parts as $part) {
			$query->unionAll($part);
		}

		return md5((string) json_encode($query->get()));
	}

	private function unpack(string $board): ?CardObjectCollection
	{
		preg_match_all('/(?:O|C):\d+:"([^"]+)"|E:\d+:"([^":]+):/', $board, $found);

		$classes = array_values(array_unique(array_filter([...$found[1], ...$found[2]])));

		foreach ($classes as $class) {
			if (! collect(self::ALLOWED_NAMESPACES)->contains(fn (string $namespace): bool => str_starts_with($class, $namespace))) {
				return null;
			}
		}

		$cardObjectCollection = @unserialize($board, ['allowed_classes' => $classes]);

		return $cardObjectCollection instanceof CardObjectCollection ? $cardObjectCollection : null;
	}
}
