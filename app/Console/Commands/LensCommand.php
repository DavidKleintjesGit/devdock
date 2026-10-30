<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Review\Actions\RunLensAction;
use Src\Review\Actions\SettleRoundAction;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundId;

class LensCommand extends Command
{
	protected $signature = 'devdock:lens {--round= : The review round to work on} {--lens= : The lens to let look}';

	protected $description = 'Let one lens judge a delivery and write its verdict back to the round';

	public function handle(): int
	{
		$round = $this->option('round');
		$lens = $this->option('lens');

		if ($round === null || $lens === null) {
			$this->components->error('A round and a lens are needed.');

			return self::FAILURE;
		}

		$reviewRoundId = new ReviewRoundId(id: (int) $round);
		$reviewRoundEntity = RunLensAction::execute($reviewRoundId, new LensId(id: (int) $lens));

		if ($reviewRoundEntity === null) {
			$this->components->error('That round or lens is gone.');

			return self::FAILURE;
		}

		// run by hand there is no batch to close the round afterwards
		$reviewRoundEntity = SettleRoundAction::execute($reviewRoundId) ?? $reviewRoundEntity;

		$this->components->info($reviewRoundEntity->status->label());

		return self::SUCCESS;
	}
}
