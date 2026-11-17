<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Enums\WorkingCopyTaskEnum;

/**
 * Hands over how the finished background tasks of a card ended that the panel
 * has not seen yet. An ending stays until the panel says it has seen it, by
 * asking again with a moment at or past it: an answer that never reached the
 * page loses nothing. Making a working copy keeps its own bar, so its ending
 * stays.
 *
 * @method static list<array{at: int, task: string, failure: ?string, notice: ?string, result: array<string, string>}> execute(string $card, int $seenUntil)
 */
class TakeWorkingCopyTaskEndingsAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly int $seenUntil,
	) {}

	/**
	 * @return list<array{at: int, task: string, failure: ?string, notice: ?string, result: array<string, string>}>
	 */
	public function handle(): array
	{
		return Cache::lock(StartWorkingCopyTaskAction::lockFor($this->card), 10)->block(5, function (): array {
			$key = StartWorkingCopyTaskAction::keyFor($this->card);
			$kept = Cache::get($key);

			if (! is_array($kept)) {
				return [];
			}

			$endings = collect((array) ($kept['endings'] ?? []));
			$isLink = fn (array $ending): bool => $ending['task'] === WorkingCopyTaskEnum::LINK->value;
			$unseen = $endings->reject(fn (array $ending): bool => $isLink($ending) || (int) ($ending['at'] ?? 0) <= $this->seenUntil)->values();
			$left = $endings->filter(fn (array $ending): bool => $isLink($ending) || (int) ($ending['at'] ?? 0) > $this->seenUntil)->values()->all();
			$isDone = ! (bool) ($kept['is_running'] ?? false) && $left === [] && ($kept['task'] ?? null) !== WorkingCopyTaskEnum::LINK->value;

			$isDone ? Cache::forget($key) : Cache::forever($key, [...$kept, 'endings' => $left]);

			return $unseen->map(fn (array $ending): array => [...$ending, 'at' => (int) ($ending['at'] ?? 0)])->all();
		});
	}
}
