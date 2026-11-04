<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Repositories\WorktreeRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see WorktreeRepository
 */
class WorktreeRepositoryCommitTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		// a checkout whose exclude does not keep DevDock's files out, as an old one may
		$this->repository = TemporaryGitRepository::create();
		file_put_contents($this->repository->path . '/.git/info/exclude', '');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function commit_leaves_the_files_devdock_writes_out_of_a_commit_of_everything(): void
	{
		$this->repository->write('.devdock/rebase.json', '{}');
		$this->repository->write('.wakatime-project', 'shop');
		$this->repository->write('billing/Invoice.php', '<?php');

		resolve(WorktreeRepositoryContract::class)->commit($this->repository->path, 'Add an invoice', '');

		$this->assertSame("billing/Invoice.php", $this->repository->git('show', '--name-only', '--format=', 'HEAD'));
	}

	#[Test]
	public function commit_leaves_a_file_devdock_writes_out_when_it_was_chosen(): void
	{
		$this->repository->write('.devdock/rebase.json', '{}');
		$this->repository->write('billing/Invoice.php', '<?php');

		resolve(WorktreeRepositoryContract::class)->commit($this->repository->path, 'Add an invoice', '', ['.devdock/rebase.json', 'billing/Invoice.php']);

		$this->assertSame("billing/Invoice.php", $this->repository->git('show', '--name-only', '--format=', 'HEAD'));
	}

	#[Test]
	public function commit_works_in_a_checkout_whose_exclude_keeps_devdocks_files_out(): void
	{
		// the exclude as DevDock writes it into every project it prepares
		file_put_contents($this->repository->path . '/.git/info/exclude', "/.devdock/\n/.wakatime-project\n");
		$this->repository->write('.devdock/rebase.json', '{}');
		$this->repository->write('.wakatime-project', 'shop');
		$this->repository->write('billing/Invoice.php', '<?php');

		$failure = resolve(WorktreeRepositoryContract::class)->commit($this->repository->path, 'Add an invoice', '', ['billing/Invoice.php']);

		$this->assertNull($failure);
		$this->assertSame('billing/Invoice.php', $this->repository->git('show', '--name-only', '--format=', 'HEAD'));
	}

	#[Test]
	public function commit_of_everything_works_in_a_checkout_whose_exclude_keeps_devdocks_files_out(): void
	{
		file_put_contents($this->repository->path . '/.git/info/exclude', "/.devdock/\n/.wakatime-project\n");
		$this->repository->write('.devdock/rebase.json', '{}');
		$this->repository->write('billing/Invoice.php', '<?php');

		$failure = resolve(WorktreeRepositoryContract::class)->commit($this->repository->path, 'Add an invoice', '');

		$this->assertNull($failure);
		$this->assertSame('billing/Invoice.php', $this->repository->git('show', '--name-only', '--format=', 'HEAD'));
	}

	#[Test]
	public function commit_refuses_when_only_files_devdock_writes_were_chosen(): void
	{
		$this->repository->write('.wakatime-project', 'shop');
		$head = $this->repository->git('rev-parse', 'HEAD');

		$failure = resolve(WorktreeRepositoryContract::class)->commit($this->repository->path, 'Nothing', '', ['.wakatime-project']);

		$this->assertNotNull($failure);
		$this->assertSame($head, $this->repository->git('rev-parse', 'HEAD'));
	}
}
