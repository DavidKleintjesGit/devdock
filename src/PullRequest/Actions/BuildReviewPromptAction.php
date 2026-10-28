<?php

declare(strict_types=1);

namespace Src\PullRequest\Actions;

use Illuminate\Support\Collection;
use Src\Packages\Cqrs\Command;
use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestId;
use Src\PullRequest\Objects\ReviewCommentEntity;

/**
 * Writes the open review remarks of one pull request out as a prompt: what was
 * said, where, and how to go about it.
 *
 * @method static ?string execute(PullRequestId $pullRequestId)
 */
class BuildReviewPromptAction extends Command
{
	public function __construct(
		public readonly PullRequestId $pullRequestId,
	) {
	}

	public function handle(PullRequestRepositoryContract $pullRequestRepository): ?string
	{
		$pullRequestEntity = $pullRequestRepository->getAll()
			->first(fn (PullRequestEntity $entity) => $entity->pullRequestId->id === $this->pullRequestId->id);

		$reviewCommentEntities = collect($pullRequestRepository->getComments($this->pullRequestId));

		// the reviewer sometimes writes out a prompt of their own; it is not a
		// finding, so it stays out of the list
		$findings = $reviewCommentEntities->reject(
			fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
		);

		if ($pullRequestEntity === null || $findings->isEmpty()) {
			return null;
		}

		$parts = [
			$this->opening($pullRequestEntity, $findings),
			$this->findings($findings),
			$this->howToGoAbout(),
		];

		return implode("\n\n", array_filter($parts));
	}

	/**
	 * @param  Collection<int, ReviewCommentEntity>  $findings
	 */
	private function opening(PullRequestEntity $pullRequestEntity, Collection $findings): string
	{
		return __(':name vroeg om wijzigingen op #:number — :title (branch :branch).', [
			'name' => $pullRequestEntity->changesRequestedBy,
			'number' => $pullRequestEntity->number,
			'title' => $pullRequestEntity->title,
			'branch' => $pullRequestEntity->sourceBranch,
		]) . "\n\n" . trans_choice(
			'Er staat nog :count opmerking open.|Er staan nog :count opmerkingen open.',
			$findings->count(),
			['count' => $findings->count()],
		);
	}

	/**
	 * Every open remark, with the replies that belong to it right underneath.
	 *
	 * @param  Collection<int, ReviewCommentEntity>  $findings
	 */
	private function findings(Collection $findings): string
	{
		$replies = $findings->filter(fn (ReviewCommentEntity $entity) => $entity->isReply())->groupBy('parentCommentId');
		$blocks = [];
		$number = 0;

		foreach ($findings->reject(fn (ReviewCommentEntity $entity) => $entity->isReply()) as $reviewCommentEntity) {
			$number++;
			$blocks[] = $this->finding($number, $reviewCommentEntity);

			foreach ($replies->get($reviewCommentEntity->commentId, collect()) as $reply) {
				$blocks[] = $this->reply($reply);
			}
		}

		// a reply whose remark was resolved stands on its own
		foreach ($replies->flatten()->reject(fn (ReviewCommentEntity $entity) => $findings->contains(
			fn (ReviewCommentEntity $parent) => $parent->commentId === $entity->parentCommentId,
		)) as $orphan) {
			$number++;
			$blocks[] = $this->finding($number, $orphan);
		}

		return implode("\n\n", $blocks);
	}

	private function finding(int $number, ReviewCommentEntity $reviewCommentEntity): string
	{
		// the weight and the heading are the reviewer's own first lines, so the
		// line above a remark only says where it hangs and who wrote it
		$heading = implode(' · ', array_filter([
			$reviewCommentEntity->location() ?? __('Op de pull request zelf'),
			$reviewCommentEntity->author,
		]));

		return $number . '. ' . $heading . "\n" . $reviewCommentEntity->withoutSignature();
	}

	private function reply(ReviewCommentEntity $reviewCommentEntity): string
	{
		return __('Reactie van :name:', ['name' => $reviewCommentEntity->author])
			. "\n" . $reviewCommentEntity->withoutSignature();
	}

	/**
	 * The way of working that holds for every remark, so nothing is changed on
	 * a hunch and nothing reaches the reviewer unasked.
	 */
	private function howToGoAbout(): string
	{
		return implode("\n", [
			__('Werkwijze:'),
			__('- Controleer per opmerking eerst of het probleem er nog is op deze branch. Een opmerking kan al opgelost zijn zonder dat iemand hem heeft afgevinkt.'),
			__('- Is het al opgelost, zeg dan welke commit dat deed en stel voor de opmerking af te vinken. Vink zelf niets af.'),
			__('- Is het nog open, pas het dan aan volgens de regels uit CLAUDE.md en draai de tests van wat je aanraakt.'),
			__('- Ben je het ergens niet mee eens, schrijf dan een voorstel voor een antwoord en laat het aan mij zien.'),
			__('- Plaats geen reacties in Bitbucket, vink geen opmerkingen af, commit niet en push niet zonder dat ik daar eerst ja op heb gezegd. Leg het voor, wacht op mijn akkoord en doe het daarna pas.'),
		]);
	}
}
