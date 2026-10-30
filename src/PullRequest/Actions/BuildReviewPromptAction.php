<?php

declare(strict_types=1);

namespace Src\PullRequest\Actions;

use Illuminate\Support\Collection;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\DescribeReportAction;
use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestId;
use Src\PullRequest\Objects\ReviewCommentEntity;

/**
 * Writes the open review remarks of a task out as a prompt: what was said,
 * where, and how to go about it. A task may carry several pull requests, and
 * they end up in one prompt.
 *
 * @method static ?string execute(array $pullRequestIds)
 */
class BuildReviewPromptAction extends Command
{
	/**
	 * @param  array<int, PullRequestId>  $pullRequestIds
	 */
	public function __construct(
		public readonly array $pullRequestIds,
	) {}

	public function handle(PullRequestRepositoryContract $pullRequestRepository): ?string
	{
		$pullRequestEntities = collect($pullRequestRepository->getAll());
		$blocks = [];
		$total = 0;
		$numbers = [];
		$asked = null;
		$branch = null;

		foreach ($this->pullRequestIds as $pullRequestId) {
			$pullRequestEntity = $pullRequestEntities->first(
				fn (PullRequestEntity $entity) => $entity->pullRequestId->id === $pullRequestId->id,
			);

			if ($pullRequestEntity === null) {
				continue;
			}

			// the reviewer sometimes writes out a prompt of their own; it is not a
			// finding, so it stays out of the list
			$findings = collect($pullRequestRepository->getComments($pullRequestId))
				->reject(fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt);

			if ($findings->isEmpty()) {
				continue;
			}

			$numbers[] = '#' . $pullRequestEntity->number;
			$asked ??= $pullRequestEntity->changesRequestedBy;
			$branch ??= $pullRequestEntity->sourceBranch;
			$total += $findings->count();

			$blocks[] = count($this->pullRequestIds) > 1
				? '#' . $pullRequestEntity->number . ' — ' . $pullRequestEntity->title . "\n\n" . $this->findings($findings)
				: $this->findings($findings);
		}

		if ($blocks === []) {
			return null;
		}

		$opening = __(':name vroeg om wijzigingen op :numbers (branch :branch).', [
			'name' => $asked,
			'numbers' => implode(' ' . __('en') . ' ', $numbers),
			'branch' => $branch,
		]) . "\n\n" . trans_choice(
			'Er staat nog :count opmerking open.|Er staan nog :count opmerkingen open.',
			$total,
			['count' => $total],
		);

		return implode("\n\n", [$opening, implode("\n\n", $blocks), $this->howToGoAbout()]);
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
			DescribeReportAction::execute(),
		]);
	}
}
