<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorkingCopyStateRepositoryContract;
use Src\Worktree\Repositories\WorkingCopyStateRepository;
use Tests\TestCase;

/**
 * @see WorkingCopyStateRepository
 */
class WorkingCopyStateRepositoryTest extends TestCase
{
	private string $folder;

	protected function setUp(): void
	{
		parent::setUp();

		$this->folder = sys_get_temp_dir() . '/devdock-stale-' . fake()->uuid();
		mkdir($this->folder . '/vendor/composer', 0777, true);
	}

	protected function tearDown(): void
	{
		@unlink($this->folder . '/composer.lock');
		@unlink($this->folder . '/vendor/composer/installed.json');
		@rmdir($this->folder . '/vendor/composer');
		@rmdir($this->folder . '/vendor');
		@rmdir($this->folder);

		parent::tearDown();
	}

	#[Test]
	public function stale_packages_names_a_package_installed_at_another_version(): void
	{
		$package = fake()->slug(2) . '/' . fake()->slug(2);
		file_put_contents($this->folder . '/composer.lock', (string) json_encode(['packages' => [['name' => $package, 'version' => 'v2.0.0']], 'packages-dev' => []]));
		file_put_contents($this->folder . '/vendor/composer/installed.json', (string) json_encode(['packages' => [['name' => $package, 'version' => 'v1.0.0']]]));

		$stalePackages = resolve(WorkingCopyStateRepositoryContract::class)->stalePackages($this->folder . '/composer.lock', $this->folder . '/vendor/composer/installed.json');

		$this->assertSame([$package], $stalePackages);
	}

	#[Test]
	public function stale_packages_names_a_package_that_is_not_installed(): void
	{
		$package = fake()->slug(2) . '/' . fake()->slug(2);
		file_put_contents($this->folder . '/composer.lock', (string) json_encode(['packages' => [], 'packages-dev' => [['name' => $package, 'version' => 'v1.0.0']]]));
		file_put_contents($this->folder . '/vendor/composer/installed.json', (string) json_encode(['packages' => []]));

		$stalePackages = resolve(WorkingCopyStateRepositoryContract::class)->stalePackages($this->folder . '/composer.lock', $this->folder . '/vendor/composer/installed.json');

		$this->assertSame([$package], $stalePackages);
	}

	#[Test]
	public function stale_packages_is_empty_when_vendor_matches_the_lock(): void
	{
		$package = fake()->slug(2) . '/' . fake()->slug(2);
		file_put_contents($this->folder . '/composer.lock', (string) json_encode(['packages' => [['name' => $package, 'version' => 'v1.0.0']], 'packages-dev' => []]));
		file_put_contents($this->folder . '/vendor/composer/installed.json', (string) json_encode(['packages' => [['name' => $package, 'version' => 'v1.0.0']]]));

		$stalePackages = resolve(WorkingCopyStateRepositoryContract::class)->stalePackages($this->folder . '/composer.lock', $this->folder . '/vendor/composer/installed.json');

		$this->assertSame([], $stalePackages);
	}

	#[Test]
	public function stale_packages_is_empty_without_installed_packages(): void
	{
		file_put_contents($this->folder . '/composer.lock', (string) json_encode(['packages' => [['name' => fake()->slug(2) . '/' . fake()->slug(2), 'version' => 'v1.0.0']]]));

		$stalePackages = resolve(WorkingCopyStateRepositoryContract::class)->stalePackages($this->folder . '/composer.lock', $this->folder . '/vendor/composer/installed.json');

		$this->assertSame([], $stalePackages);
	}
}
