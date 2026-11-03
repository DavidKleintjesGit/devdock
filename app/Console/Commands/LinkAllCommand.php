<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Worktree\Actions\GetLinkPlanAction;
use Src\Worktree\Actions\LinkWorkingCopyAction;
use Src\Worktree\Actions\StartRepairAction;
use Src\Worktree\Enums\LinkStepEnum;
use Src\Worktree\Objects\LinkPlanObject;

/**
 * Walks every card that is not done and says what it needs before an agent
 * can work on it. Only with --apply does it link and repair: a card without a
 * working copy of its own gets one, and a copy with open points is repaired
 * in the background.
 */
class LinkAllCommand extends Command
{
	protected $signature = 'devdock:link-all {--apply : Link and repair instead of only listing} {--card= : Only this card}';

	protected $description = 'List, and with --apply link and repair, the working copy of every card on the board';

	public function handle(): int
	{
		$linkPlans = GetLinkPlanAction::execute($this->option('card') ?: null);

		$this->table(
			['Card', 'Project', 'Branch', 'Step', 'Detail'],
			array_map(fn (LinkPlanObject $linkPlanObject): array => [
				$linkPlanObject->card,
				$linkPlanObject->project,
				$linkPlanObject->branch ?? '-',
				$linkPlanObject->linkStep->label(),
				$linkPlanObject->detail,
			], $linkPlans),
		);

		if (! $this->option('apply')) {
			$this->components->info('Nothing was changed. Run again with --apply to link and repair.');

			return self::SUCCESS;
		}

		foreach ($linkPlans as $linkPlanObject) {
			$failure = match ($linkPlanObject->linkStep) {
				LinkStepEnum::LINK => LinkWorkingCopyAction::execute($linkPlanObject->card, (string) $linkPlanObject->project, (string) $linkPlanObject->branch),
				LinkStepEnum::REPAIR => StartRepairAction::execute((string) $linkPlanObject->path) ? null : 'A repair is already running.',
				default => null,
			};

			if ($failure !== null) {
				$this->components->error($linkPlanObject->card . ': ' . $failure);
			} elseif (in_array($linkPlanObject->linkStep, [LinkStepEnum::LINK, LinkStepEnum::REPAIR], true)) {
				$this->components->info($linkPlanObject->card . ': ' . $linkPlanObject->linkStep->label());
			}
		}

		return self::SUCCESS;
	}
}
