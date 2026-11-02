<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensEntity;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;
use Src\Task\Actions\GetReadableDirectoriesAction;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;
use Throwable;

/**
 * Lets one lens look at the delivery and writes down what it found. The lens
 * starts without the builder's session, so it judges the work and not the
 * reasoning that produced it.
 *
 * It runs on the queue, so five lenses look at the same delivery side by side
 * and the page that sent them is free straight away.
 *
 * @method static ?ReviewRoundEntity execute(ReviewRoundId $reviewRoundId, LensId $lensId)
 */
class RunLensAction extends Command implements ShouldQueue
{
	use Batchable;
	use InteractsWithQueue;
	use Queueable;

	public int $tries = 1;

	/**
	 * Above the process timeout, so the lens itself is what gives up first and
	 * its reason is the one that gets written down.
	 */
	public int $timeout = 1860;

	private int $toolCalls = 0;

	/**
	 * The last message of the lens that held a verdict block.
	 */
	private ?string $verdictMessage = null;

	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
		public readonly LensId $lensId,
	) {}

	public function handle(
		ReviewRoundRepositoryContract $reviewRoundRepository,
		LensRepositoryContract $lensRepository,
		ClaudeProcessRepositoryContract $claudeProcessRepository,
		WorktreeRepositoryContract $worktreeRepository,
	): ?ReviewRoundEntity {
		$reviewRoundEntity = $reviewRoundRepository->getById($this->reviewRoundId);

		// the lens as this project set it: its own paths, rules and command
		$project = GetWorktreesAction::execute()
			->first(fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->path === $reviewRoundEntity?->directory)
			?->project;

		$lensEntity = $lensRepository->getAll($project)->first(
			fn (LensEntity $lensEntity) => $lensEntity->lensId->id === $this->lensId->id,
		);

		if ($reviewRoundEntity === null || $lensEntity === null || $reviewRoundEntity->directory === null) {
			return null;
		}

		$directory = $reviewRoundEntity->directory;

		$reviewRoundRepository->markResultRunning($this->reviewRoundId, $this->lensId, null);

		$answer = $claudeProcessRepository->watch(
			$directory,
			WriteLensContextAction::execute(
				$directory,
				$lensEntity,
				$lensEntity->touches($worktreeRepository->getChangedFiles($directory)),
				$this->previousReport($reviewRoundRepository, $reviewRoundEntity),
				$project === null ? [] : $this->rulesFor($project, $lensEntity),
			),
			$this->logPath($lensEntity),
			fn (array $event) => $this->note($reviewRoundRepository, $event),
			GetReadableDirectoriesAction::execute($reviewRoundEntity->card),
		);

		// the answer is only the last message; a lens that was woken again after
		// its verdict, by a background task finishing, ends on a line without it
		$verdictObject = ReadVerdictAction::execute(
			str_contains((string) $answer, '```verdict') ? $answer : ($this->verdictMessage ?? $answer),
		);

		$reviewRoundEntity = $reviewRoundRepository->writeResult(
			$this->reviewRoundId,
			$this->lensId,
			$verdictObject->verdict,
			$verdictObject->findings,
			$verdictObject->report,
		);

		return CloseRoundAction::execute($this->reviewRoundId) ?? $reviewRoundEntity;
	}

	/**
	 * A lens that fell over is a lens with a verdict. Leaving it on running
	 * would hold up the whole round for something that is already gone.
	 */
	public function failed(Throwable $throwable): void
	{
		$reviewRoundRepository = resolve(ReviewRoundRepositoryContract::class);
		$reviewRoundEntity = $reviewRoundRepository->getById($this->reviewRoundId);

		foreach ($reviewRoundEntity?->results ?? [] as $resultEntity) {
			// a verdict that already stands is the real one; this is a worker
			// reporting back about work somebody else finished
			if ($resultEntity->lensId->id === $this->lensId->id && $resultEntity->verdict->isSettled()) {
				return;
			}
		}

		$reviewRoundRepository->writeResult(
			$this->reviewRoundId,
			$this->lensId,
			VerdictEnum::ERROR,
			0,
			$throwable->getMessage(),
		);

		CloseRoundAction::execute($this->reviewRoundId);
	}

	/**
	 * Keeps the round up to date with what this lens is doing. Claude says it
	 * in its own words, so nothing has to be guessed from tool names.
	 */
	private function note(ReviewRoundRepositoryContract $reviewRoundRepository, array $event): void
	{
		$activity = null;
		$sessionUuid = null;
		$cost = isset($event['total_cost_usd']) ? (float) $event['total_cost_usd'] : null;

		if (($event['subtype'] ?? null) === 'init') {
			$sessionUuid = $event['session_id'] ?? null;
		}

		if (($event['subtype'] ?? null) === 'task_summary' && filled($event['detail'] ?? null)) {
			$activity = (string) $event['detail'];
		}

		if (($event['type'] ?? null) === 'assistant') {
			$this->rememberVerdict($event);
			$this->toolCalls += count($this->toolNames($event));
			$activity ??= $this->toolNames($event)[0] ?? null;
		}

		if ($activity === null && $sessionUuid === null && $cost === null) {
			return;
		}

		$reviewRoundRepository->markResultProgress(
			$this->reviewRoundId,
			$this->lensId,
			$activity,
			$this->toolCalls,
			$cost,
			$sessionUuid,
		);
	}

	private function rememberVerdict(array $event): void
	{
		foreach ($event['message']['content'] ?? [] as $block) {
			if (($block['type'] ?? null) === 'text' && str_contains((string) ($block['text'] ?? ''), '```verdict')) {
				$this->verdictMessage = (string) $block['text'];
			}
		}
	}

	/**
	 * The tools this message reaches for, in the order it names them.
	 *
	 * @return list<string>
	 */
	private function toolNames(array $event): array
	{
		$names = [];

		foreach ($event['message']['content'] ?? [] as $block) {
			if (($block['type'] ?? null) === 'tool_use' && filled($block['name'] ?? null)) {
				$names[] = (string) $block['name'];
			}
		}

		return $names;
	}

	/**
	 * Where everything this lens sent back is kept, so a round that went wrong
	 * can still be read afterwards.
	 */
	private function logPath(LensEntity $lensEntity): string
	{
		return resolve(ReviewRoundRepositoryContract::class)->logFolder($this->reviewRoundId) . '/' . $lensEntity->name . '.jsonl';
	}

	/**
	 * The rule files this lens reads in the project, found the way the
	 * workflow page shows them.
	 *
	 * @return array<int, string>
	 */
	private function rulesFor(string $project, LensEntity $lensEntity): array
	{
		$checked = collect(CheckWorkflowAction::execute($project)['lenses'])
			->first(fn (array $lens) => $lens['lens']->lensId->id === $lensEntity->lensId->id);

		return $checked['reads'] ?? [];
	}

	/**
	 * What this lens left open in the round before, so it can tick its own
	 * points off instead of starting over.
	 */
	private function previousReport(
		ReviewRoundRepositoryContract $reviewRoundRepository,
		ReviewRoundEntity $reviewRoundEntity,
	): ?string {
		foreach ($reviewRoundRepository->getForCard($reviewRoundEntity->card) as $earlier) {
			if ($earlier->number !== $reviewRoundEntity->number - 1) {
				continue;
			}

			foreach ($earlier->results as $resultEntity) {
				if ($resultEntity->lensId->id === $this->lensId->id && $resultEntity->verdict === VerdictEnum::FAIL) {
					return $resultEntity->report;
				}
			}
		}

		return null;
	}
}
