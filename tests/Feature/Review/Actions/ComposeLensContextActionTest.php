<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Review\Actions\ComposeLensContextAction;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Models\Lens;
use Src\Review\Objects\LensEntity;
use Tests\TestCase;

/**
 * @see ComposeLensContextAction
 */
class ComposeLensContextActionTest extends TestCase
{
	#[Test]
	public function execute_has_the_lens_read_the_working_files_against_the_base_of_the_branch(): void
	{
		$lens = Lens::factory()->create();
		$lensEntity = resolve(LensRepositoryContract::class)->getAll()->first(fn (LensEntity $lensEntity): bool => $lensEntity->lensId->id === $lens->id);

		$context = ComposeLensContextAction::execute($lensEntity, ['billing/Invoice.php', 'billing/Credit.php'], null, [], 'a1b2c3d4', ['billing/Credit.php']);

		// without HEAD the diff takes in what the agent changed and did not commit
		$this->assertStringContainsString('git diff a1b2c3d4 -- ', $context);
		$this->assertStringContainsString(__('Nieuw en nog niet bij git bekend, dus niet in git diff; lees ze in zijn geheel:') . "\n- billing/Credit.php", $context);
	}

	#[Test]
	public function execute_names_no_command_without_a_known_base(): void
	{
		$lens = Lens::factory()->create();
		$lensEntity = resolve(LensRepositoryContract::class)->getAll()->first(fn (LensEntity $lensEntity): bool => $lensEntity->lensId->id === $lens->id);

		$context = ComposeLensContextAction::execute($lensEntity, ['billing/Invoice.php']);

		$this->assertStringNotContainsString('git diff', $context);
	}
}
