<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Src\Board\Objects\CardObjectCollection;
use Src\Packages\Cqrs\Command;

/**
 * The board as BuildBoardAction makes it, kept until a row under it changes.
 * One click asks for the board in the panel, the board and the layout; this
 * builds it once for all of them, and again only when something moved.
 *
 * @method static CardObjectCollection execute()
 */
class GetBoardAction extends Command
{
	private const KEY = 'board.cards';

	/**
	 * The tables the board is made of, with the column that says when a row
	 * last changed. A change in any of them, a row added, changed or taken
	 * away, makes the board build again.
	 */
	private const TABLES = [
		'card_views' => 'updated_at',
		'claude_sessions' => 'updated_at',
		// only a finished round counts: it brings the git state the cards show, while a running one writes its progress on every source
		'fetch_runs' => 'finished_at',
		'project_workflows' => 'updated_at',
		'projects' => 'updated_at',
		'prompt_runs' => 'updated_at',
		'pull_requests' => 'updated_at',
		'review_results' => 'updated_at',
		'review_rounds' => 'updated_at',
		'task_agents' => 'updated_at',
		'task_links' => 'updated_at',
		'tasks' => 'updated_at',
		'tickets' => 'updated_at',
		'todos' => 'updated_at',
		'worktrees' => 'updated_at',
	];

	/**
	 * The board stores as plain text, since the cache refuses objects; reading
	 * it back allows only the classes a board is made of.
	 */
	private const ALLOWED_NAMESPACES = ['Src\\', 'Carbon\\'];

	public function handle(): CardObjectCollection
	{
		$version = $this->version();
		$kept = Cache::get(self::KEY);

		if (is_array($kept) && $kept['version'] === $version) {
			$cardObjectCollection = $this->unpack((string) $kept['board']);

			if ($cardObjectCollection !== null) {
				return $cardObjectCollection;
			}
		}

		$cardObjectCollection = BuildBoardAction::execute();

		// the git state on the cards has its own cache of this length; the board never outlives it
		Cache::put(self::KEY, ['version' => $version, 'board' => serialize($cardObjectCollection)], (int) config('devdock.worktree.status_seconds'));

		return $cardObjectCollection;
	}

	/**
	 * How many rows each table holds and when one last changed, in one query:
	 * it runs on every click, so one round trip instead of thirty. Rows whose
	 * column is still empty, such as a round that has not finished, are not counted.
	 */
	private function version(): string
	{
		$parts = array_map(
			fn (string $table, string $column): Builder => DB::table($table)->selectRaw("? as name, count({$column}) as row_count, max({$column}) as changed_at", [$table]),
			array_keys(self::TABLES),
			self::TABLES,
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
