<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Enums\WorkingCopyTaskEnum;

/**
 * Hands over how the finished background tasks of a card ended, once: what
 * is taken here, the panel shows and the card forgets. Making a working copy
 * keeps its own bar, so its ending stays.
 *
 * @method static list<array{task: string, failure: ?string, notice: ?string, result: array<string, string>}> execute(string $card)
 */
class TakeWorkingCopyTaskEndingsAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	/**
	 * @return list<array{task: string, failure: ?string, notice: ?string, result: array<string, string>}>
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
			$taken = $endings->reject(fn (array $ending): bool => $ending['task'] === WorkingCopyTaskEnum::LINK->value)->values()->all();
			$left = $endings->filter(fn (array $ending): bool => $ending['task'] === WorkingCopyTaskEnum::LINK->value)->values()->all();
			$isDone = ! (bool) ($kept['is_running'] ?? false) && $left === [] && ($kept['task'] ?? null) !== WorkingCopyTaskEnum::LINK->value;

			$isDone ? Cache::forget($key) : Cache::forever($key, [...$kept, 'endings' => $left]);

			return $taken;
		});
	}
}
