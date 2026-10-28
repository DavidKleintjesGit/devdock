<?php

declare(strict_types=1);

namespace Src\PullRequest\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\PullRequest\Enums\PullRequestRoleEnum;
use Src\PullRequest\Enums\PullRequestStateEnum;
use Src\PullRequest\Objects\PullRequestObject;
use Src\PullRequest\Objects\PullRequestObjectCollection;
use Src\PullRequest\Objects\ReviewCommentObject;
use Src\PullRequest\Objects\ReviewCommentObjectCollection;

class BitbucketPullRequestRepository implements PullRequestSourceRepositoryContract
{
	public function isConfigured(): bool
	{
		return filled(config('devdock.bitbucket.workspace'))
			&& filled(config('devdock.atlassian.email'))
			&& filled(config('devdock.bitbucket.token'));
	}

	public function getOpenAndMerged(?string $reviewerName, CarbonImmutable $mergedSince): PullRequestObjectCollection
	{
		$pullRequestObjects = [];

		foreach ($this->repositorySlugs() as $repository) {
			$pullRequests = [
				...$this->pullRequests($repository, 'OPEN'),
				...$this->pullRequests($repository, 'MERGED', [
					'q' => 'updated_on>=' . $mergedSince->toIso8601String(),
				]),
			];

			foreach ($pullRequests as $pullRequest) {
				$role = $this->roleOf($pullRequest, $reviewerName);

				if ($reviewerName !== null && $role === null) {
					continue;
				}

				// a pull request that was declined after it was opened comes back
				// in the open listing; the board only knows open and merged
				if (PullRequestStateEnum::tryFrom((string) ($pullRequest['state'] ?? '')) === null) {
					continue;
				}

				$pullRequestObjects[] = PullRequestObject::from(
					$this->attributesOf($pullRequest) + [
						'repository' => $repository,
						'number' => $pullRequest['id'],
						'my_role' => $role?->value,
					],
				);
			}
		}

		return PullRequestObjectCollection::fromArray($pullRequestObjects);
	}

	public function getOpenComments(string $repository, int $number): ReviewCommentObjectCollection
	{
		$workspace = config('devdock.bitbucket.workspace');
		$reviewCommentObjects = [];

		$comments = $this->pages("/2.0/repositories/{$workspace}/{$repository}/pullrequests/{$number}/comments", [
			'pagelen' => 100,

			// without the sub-fields a resolved remark comes back as an empty
			// object, which reads the same as one nobody touched
			'fields' => '+values.resolution.created_on,+values.resolution.user.display_name',
		]);

		foreach ($comments as $comment) {
			if ($this->isSettled($comment)) {
				continue;
			}

			$reviewCommentObjects[] = ReviewCommentObject::from($comment);
		}

		return ReviewCommentObjectCollection::fromArray($reviewCommentObjects);
	}

	/**
	 * Whether this remark is off the table: resolved, withdrawn, or still a
	 * draft of the reviewer.
	 */
	private function isSettled(array $comment): bool
	{
		return isset($comment['resolution']['created_on'])
			|| ($comment['deleted'] ?? false)
			|| ($comment['pending'] ?? false);
	}

	/**
	 * The slug of every repository in the workspace.
	 *
	 * @return list<string>
	 */
	private function repositorySlugs(): array
	{
		$workspace = config('devdock.bitbucket.workspace');

		// no fields filter: Bitbucket filters the error message away with it as well
		return collect($this->pages("/2.0/repositories/{$workspace}", ['pagelen' => 100]))
			->pluck('slug')
			->all();
	}

	private function pullRequests(string $repository, string $state, array $query = []): array
	{
		$workspace = config('devdock.bitbucket.workspace');

		return $this->pages("/2.0/repositories/{$workspace}/{$repository}/pullrequests", $query + [
			'state' => $state,
			'pagelen' => 50,

			// the list endpoint leaves participants out by default; without this
			// extension there is no reviewer verdict at all to sort on
			'fields' => '+values.participants,-values.description,-values.summary',
		]);
	}

	private function pages(string $path, array $query): array
	{
		$values = [];
		$page = 1;

		do {
			$result = $this->client()->get($path, $query + ['page' => $page]);

			// the message sits in the answer; after ->throw() it can no longer be read
			if ($result->failed()) {
				throw new RuntimeException($this->reason($result));
			}

			$response = $result->json();
			$values = array_merge($values, $response['values'] ?? []);
			$page++;
		} while (filled($response['next'] ?? null));

		return $values;
	}

	/**
	 * Whether the given person wrote this pull request or was asked to judge it.
	 */
	private function roleOf(array $pullRequest, ?string $reviewerName): ?PullRequestRoleEnum
	{
		if ($reviewerName === null) {
			return null;
		}

		if (($pullRequest['author']['display_name'] ?? '') === $reviewerName) {
			return PullRequestRoleEnum::AUTHOR;
		}

		$isReviewer = collect($pullRequest['participants'] ?? [])
			->contains(fn (array $participant) => ($participant['user']['display_name'] ?? '') === $reviewerName);

		return $isReviewer ? PullRequestRoleEnum::REVIEWER : null;
	}

	private function attributesOf(array $pullRequest): array
	{
		/** @var Collection<int, array> $reviewers */
		$reviewers = collect($pullRequest['participants'] ?? [])->where('role', 'REVIEWER');

		return [
			'title' => $pullRequest['title'] ?? '',
			'state' => $pullRequest['state'] ?? 'OPEN',
			'draft' => $pullRequest['draft'] ?? false,
			'author' => $pullRequest['author']['display_name'] ?? null,
			'source_branch' => $pullRequest['source']['branch']['name'] ?? null,
			'destination_branch' => $pullRequest['destination']['branch']['name'] ?? null,
			'approvals' => $reviewers->where('approved', true)->count(),
			'reviewers' => $reviewers->count(),
			'changes_requested_by' => $reviewers
				->where('state', 'changes_requested')
				->pluck('user.display_name')
				->filter()
				->implode(', ') ?: null,
			'comment_count' => $pullRequest['comment_count'] ?? 0,
			'url' => $pullRequest['links']['html']['href'] ?? null,
			'changed_at' => $pullRequest['updated_on'] ?? null,
		];
	}

	/**
	 * What went wrong, in words. On a 401 the answer is sometimes empty; the
	 * token or a missing scope is then the only sensible explanation.
	 */
	private function reason(Response $result): string
	{
		$message = $result->json('error.message') ?? $result->json('errorMessages.0');

		if (filled($message)) {
			return $message;
		}

		return $result->status() === 401
			? __(':service weigert het token. Controleer het token en zijn scopes.', ['service' => 'Bitbucket'])
			: __(':service gaf status :status.', ['service' => 'Bitbucket', 'status' => $result->status()]);
	}

	private function client(): PendingRequest
	{
		return Http::baseUrl('https://api.bitbucket.org')
			->withBasicAuth(config('devdock.atlassian.email'), config('devdock.bitbucket.token'))
			->acceptJson()
			->timeout(20)
			->retry(2, 200, throw: false);
	}
}
