<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Enums\FetchSourceEnum;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Fetch\Objects\FetchRunId;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\ScanProjectsAction;
use Src\PullRequest\Actions\FetchPullRequestsAction;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\Rule\Actions\ScanRulesAction;
use Src\Session\Actions\ScanSessionsAction;
use Src\Skill\Actions\ScanSkillsAction;
use Src\Ticket\Actions\FetchTicketsAction;
use Src\Ticket\Contracts\TicketSourceRepositoryContract;
use Throwable;

/**
 * Walks along the sources and writes down per source what it yielded. A
 * source that cannot be reached does not hold up the round.
 *
 * @method static FetchRunEntity execute(FetchRunId $fetchRunId, ?FetchSourceEnum $only)
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

		return $fetchRunRepository->markFinished(
			$this->fetchRunId,
			$results,
			$failures === [] ? null : implode(' · ', $failures),
		);
	}

	private function countFrom(FetchSourceEnum $source): int
	{
		return match ($source) {
			FetchSourceEnum::PROJECTS => count(ScanProjectsAction::execute()),
			FetchSourceEnum::SESSIONS => count(ScanSessionsAction::execute()),
			FetchSourceEnum::RULES => count(ScanRulesAction::execute()),
			FetchSourceEnum::SKILLS => count(ScanSkillsAction::execute()),
			FetchSourceEnum::TICKETS => count(FetchTicketsAction::execute()),
			FetchSourceEnum::PULL_REQUESTS => count(FetchPullRequestsAction::execute()),
		};
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
