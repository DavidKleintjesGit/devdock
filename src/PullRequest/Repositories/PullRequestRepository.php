<?php

declare(strict_types=1);

namespace Src\PullRequest\Repositories;

use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Enums\PullRequestRoleEnum;
use Src\PullRequest\Enums\PullRequestStateEnum;
use Src\PullRequest\Models\PullRequest as PullRequestEloquent;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestEntityCollection;
use Src\PullRequest\Objects\PullRequestId;
use Src\PullRequest\Objects\PullRequestObject;
use Src\PullRequest\Objects\PullRequestObjectCollection;
use Src\PullRequest\Models\ReviewComment as ReviewCommentEloquent;
use Src\PullRequest\Objects\ReviewCommentEntity;
use Src\PullRequest\Objects\ReviewCommentEntityCollection;
use Src\PullRequest\Objects\ReviewCommentId;
use Src\PullRequest\Objects\ReviewCommentObject;
use Src\PullRequest\Objects\ReviewCommentObjectCollection;

class PullRequestRepository implements PullRequestRepositoryContract
{
	public function create(PullRequestObject $pullRequestObject): PullRequestEntity
	{
		$pullRequestEloquent = PullRequestEloquent::query()->updateOrCreate(
			[
				'repository' => $pullRequestObject->repository,
				'number' => $pullRequestObject->number,
			],
			[
				'title' => $pullRequestObject->title,
				'state' => $pullRequestObject->state->value,
				'draft' => $pullRequestObject->isDraft,
				'author' => $pullRequestObject->author,
				'my_role' => $pullRequestObject->myRole?->value,
				'source_branch' => $pullRequestObject->sourceBranch,
				'destination_branch' => $pullRequestObject->destinationBranch,
				'approvals' => $pullRequestObject->approvals,
				'reviewers' => $pullRequestObject->reviewers,
				'changes_requested_by' => $pullRequestObject->changesRequestedBy,
				'comment_count' => $pullRequestObject->commentCount,
				'url' => $pullRequestObject->url,
				'changed_at' => $pullRequestObject->changedAt,
			],
		);

		return $this->mapToEntity($pullRequestEloquent);
	}

	public function getAll(): PullRequestEntityCollection
	{
		$pullRequestEntities = PullRequestEloquent::query()
			->orderByDesc('changed_at')
			->get()
			->map(fn (PullRequestEloquent $pullRequestEloquent) => $this->mapToEntity($pullRequestEloquent))
			->all();

		return PullRequestEntityCollection::fromArray($pullRequestEntities);
	}

	public function replaceAll(PullRequestObjectCollection $pullRequestObjects): PullRequestEntityCollection
	{
		$identifiers = [];

		foreach ($pullRequestObjects as $pullRequestObject) {
			$this->create($pullRequestObject);
			$identifiers[] = $pullRequestObject->repository . '#' . $pullRequestObject->number;
		}

		if ($identifiers !== []) {
			PullRequestEloquent::query()->get()
				->reject(fn (PullRequestEloquent $pullRequestEloquent) => in_array(
					$pullRequestEloquent->repository . '#' . $pullRequestEloquent->number,
					$identifiers,
					true,
				))
				->each(fn (PullRequestEloquent $pullRequestEloquent) => $pullRequestEloquent->delete());
		}

		return $this->getAll();
	}

	public function getComments(PullRequestId $pullRequestId): ReviewCommentEntityCollection
	{
		$reviewCommentEntities = ReviewCommentEloquent::query()
			->where('pull_request_id', $pullRequestId->id)
			->orderBy('path')
			->orderBy('line')
			->get()
			->map(fn (ReviewCommentEloquent $reviewCommentEloquent) => $this->mapCommentToEntity($reviewCommentEloquent))
			->all();

		return ReviewCommentEntityCollection::fromArray($reviewCommentEntities);
	}

	public function replaceComments(
		PullRequestId $pullRequestId,
		ReviewCommentObjectCollection $reviewCommentObjects,
	): ReviewCommentEntityCollection {
		$commentIds = [];

		foreach ($reviewCommentObjects as $reviewCommentObject) {
			ReviewCommentEloquent::query()->updateOrCreate(
				['comment_id' => $reviewCommentObject->commentId],
				[
					'pull_request_id' => $pullRequestId->id,
					'parent_comment_id' => $reviewCommentObject->parentCommentId,
					'author' => $reviewCommentObject->author,
					'body' => $reviewCommentObject->body,
					'path' => $reviewCommentObject->path,
					'line' => $reviewCommentObject->line,
					'is_prompt' => $reviewCommentObject->isPrompt,
					'url' => $reviewCommentObject->url,
					'commented_at' => $reviewCommentObject->commentedAt,
				],
			);

			$commentIds[] = $reviewCommentObject->commentId;
		}

		// a remark that was resolved since the last round is no longer open,
		// so it leaves the board with it
		ReviewCommentEloquent::query()
			->where('pull_request_id', $pullRequestId->id)
			->whereNotIn('comment_id', $commentIds)
			->delete();

		return $this->getComments($pullRequestId);
	}

	private function mapCommentToEntity(ReviewCommentEloquent $reviewCommentEloquent): ReviewCommentEntity
	{
		return new ReviewCommentEntity(
			reviewCommentId: new ReviewCommentId(id: $reviewCommentEloquent->id),
			pullRequestId: new PullRequestId(id: (int) $reviewCommentEloquent->pull_request_id),
			commentId: (int) $reviewCommentEloquent->comment_id,
			parentCommentId: $reviewCommentEloquent->parent_comment_id === null
				? null
				: (int) $reviewCommentEloquent->parent_comment_id,
			author: $reviewCommentEloquent->author,
			body: (string) $reviewCommentEloquent->body,
			path: $reviewCommentEloquent->path,
			line: $reviewCommentEloquent->line === null ? null : (int) $reviewCommentEloquent->line,
			isPrompt: (bool) $reviewCommentEloquent->is_prompt,
			url: $reviewCommentEloquent->url,
			commentedAt: $reviewCommentEloquent->commented_at,
		);
	}

	private function mapToEntity(PullRequestEloquent $pullRequestEloquent): PullRequestEntity
	{
		return new PullRequestEntity(
			pullRequestId: new PullRequestId(id: $pullRequestEloquent->id),
			repository: (string) $pullRequestEloquent->repository,
			number: (int) $pullRequestEloquent->number,
			title: (string) $pullRequestEloquent->title,
			state: PullRequestStateEnum::from((string) $pullRequestEloquent->state),
			isDraft: (bool) $pullRequestEloquent->draft,
			author: $pullRequestEloquent->author,
			myRole: $pullRequestEloquent->my_role === null
				? null
				: PullRequestRoleEnum::from((string) $pullRequestEloquent->my_role),
			sourceBranch: $pullRequestEloquent->source_branch,
			destinationBranch: $pullRequestEloquent->destination_branch,
			approvals: (int) $pullRequestEloquent->approvals,
			reviewers: (int) $pullRequestEloquent->reviewers,
			changesRequestedBy: $pullRequestEloquent->changes_requested_by,
			commentCount: (int) $pullRequestEloquent->comment_count,
			url: $pullRequestEloquent->url,
			changedAt: $pullRequestEloquent->changed_at,
		);
	}
}
