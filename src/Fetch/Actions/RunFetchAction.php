<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Src\Board\Actions\GetBoardAction;
use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Enums\FetchSourceEnum;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Fetch\Objects\FetchRunId;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\ScanProjectsAction;
use Src\PullRequest\Actions\FetchPullRequestsAction;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\Review\Actions\ResumeReviewLoopsAction;
use Src\Rule\Actions\ScanRulesAction;
use Src\Session\Actions\ScanSessionsAction;
use Src\Skill\Actions\ScanSkillsAction;
use Src\Ticket\Actions\FetchTicketsAction;
use Src\Ticket\Contracts\TicketSourceRepositoryContract;
use Src\Worktree\Actions\FollowPullRequestTargetsAction;
use Src\Worktree\Actions\ForgetMovedLinksAction;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Throwable;

/**
 * Walks along the sources and writes down per source what it yielded. A
 * source that cannot be reached does not hold up the round.
 *
 * @method static FetchRunEntity execute(FetchRunId $fetchRunId, ?FetchSourceEnum $only, bool $dueOnly)
 */
class RunFetchAction extends Command
{
	/**
	 * How many characters of an error message fit on the board.
	 */
	private const REASON_LENGTH = 140;

	public function __construct(
		public readonly FetchRunId $fetchRunId,
		public readonly ?FetchSourceEnum $only = null,
		// een automatische ronde laat een bron die nog rust met rust
		public readonly bool $dueOnly = false,
	) {
	}

	public function handle(FetchRunRepositoryContract $fetchRunRepository): FetchRunEntity
	{
		$results = [];
		$failures = [];

		foreach (FetchSourceEnum::cases() as $source) {
			if ($this->only !== null && $this->only !== $source) {
				continue;
			}

			if ($this->dueOnly && $this->isResting($source, $fetchRunRepository)) {
				continue;
			}

			$fetchRunRepository->markSource($this->fetchRunId, $source);

			$reasonToSkip = $this->reasonToSkip($source);

			if ($reasonToSkip !== null) {
				$results[$source->value] = 'skipped';
				$failures[] = $source->value . ': ' . $reasonToSkip;

				continue;
			}

			try {
				$results[$source->value] = $this->countFrom($source);
			} catch (Throwable $failure) {
				$results[$source->value] = 'failed';
				$failures[] = $source->value . ': ' . $this->shortened($failure);
			}
		}

		// de stand van de werkkopieën ligt dan klaar tot de volgende ronde; een
		// pagina die hem leest hoeft niet meer op git te wachten
		try {
			resolve(WorktreeRepositoryContract::class)->refreshStatuses($this->statusKeepSeconds());
		} catch (Throwable $failure) {
			$failures[] = 'worktrees: ' . $this->shortened($failure);
		}

		// how far each branch is behind and where it would clash, for the cards
		try {
			resolve(BranchStandingRepositoryContract::class)->refresh($this->statusKeepSeconds());
		} catch (Throwable $failure) {
			$failures[] = 'branches: ' . $this->shortened($failure);
		}

		// a linked folder that was put on another branch outside DevDock lets go of its task
		try {
			ForgetMovedLinksAction::execute();
		} catch (Throwable $failure) {
			$failures[] = 'links: ' . $this->shortened($failure);
		}

		// a review loop that lost its way is picked up here, at most a round later
		try {
			ResumeReviewLoopsAction::execute();
		} catch (Throwable $failure) {
			$failures[] = 'review: ' . $this->shortened($failure);
		}

		$fetchRunEntity = $fetchRunRepository->markFinished(
			$this->fetchRunId,
			$results,
			$failures === [] ? null : implode(' · ', $failures),
		);

		// the board the open pages redraw with once the round is done lies ready
		// for them; it is built after the round is marked finished, since that changes it too
		try {
			GetBoardAction::execute();
		} catch (Throwable $failure) {
			// the round itself is done; a page that finds no board ready builds it itself
			report($failure);
		}

		return $fetchRunEntity;
	}

	/**
	 * Long enough to bridge the gap to the next automatic round.
	 */
	private function statusKeepSeconds(): int
	{
		return max((int) config('devdock.worktree.status_seconds'), (int) config('devdock.refresh_seconds') * 3);
	}

	private function isResting(FetchSourceEnum $source, FetchRunRepositoryContract $fetchRunRepository): bool
	{
		if ($source->restSeconds() <= 0) {
			return false;
		}

		$lastFetchedAt = $fetchRunRepository->getLastFetchedAt($source);

		return $lastFetchedAt !== null && $lastFetchedAt->isAfter(CarbonImmutable::now()->subSeconds($source->restSeconds()));
	}

	private function countFrom(FetchSourceEnum $source): int
	{
		return match ($source) {
			FetchSourceEnum::PROJECTS => count(ScanProjectsAction::execute()),
			FetchSourceEnum::SESSIONS => count(ScanSessionsAction::execute()),
			FetchSourceEnum::RULES => count(ScanRulesAction::execute()),
			FetchSourceEnum::SKILLS => count(ScanSkillsAction::execute()),
			FetchSourceEnum::TICKETS => count(FetchTicketsAction::execute()),
			FetchSourceEnum::PULL_REQUESTS => $this->fetchPullRequests(),
		};
	}

	/**
	 * Fetches the pull requests and points every working copy at the branch its pull request merges into.
	 */
	private function fetchPullRequests(): int
	{
		$pullRequestCount = count(FetchPullRequestsAction::execute());
		FollowPullRequestTargetsAction::execute();

		return $pullRequestCount;
	}

	/**
	 * Why this source cannot be read, in words; null means it is simply up next.
	 */
	private function reasonToSkip(FetchSourceEnum $source): ?string
	{
		return match ($source) {
			FetchSourceEnum::PROJECTS => $this->missingFolder('sites', 'DEVDOCK_SITES_PATH'),
			FetchSourceEnum::SESSIONS => $this->missingFolder('sessions', 'DEVDOCK_SESSIONS_PATH'),
			FetchSourceEnum::RULES => $this->missingFolder('core', 'DEVDOCK_CORE_PATH'),
			FetchSourceEnum::TICKETS => resolve(TicketSourceRepositoryContract::class)->isConfigured()
				? null
				: __('geen Atlassian-token in .env'),
			FetchSourceEnum::PULL_REQUESTS => resolve(PullRequestSourceRepositoryContract::class)->isConfigured()
				? null
				: __('geen Atlassian-token in .env'),
			FetchSourceEnum::SKILLS => null,
		};
	}

	private function missingFolder(string $key, string $setting): ?string
	{
		return is_dir((string) config('devdock.paths.' . $key))
			? null
			: __(':setting bestaat niet', ['setting' => $setting]);
	}

	/**
	 * The heart of an error message; a json answer from an api is too long for a
	 * line on the board.
	 */
	private function shortened(Throwable $failure): string
	{
		$body = $failure instanceof RequestException
			? ($failure->response->json('error.message')
				?? $failure->response->json('errorMessages.0')
				?? $failure->response->body())
			: $failure->getMessage();

		return Str::of($body ?: $failure->getMessage())->squish()->limit(self::REASON_LENGTH)->value();
	}
}
