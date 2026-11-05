<?php

declare(strict_types=1);

namespace Tests\Feature\Project\Repositories;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Src\Project\Contracts\ProjectPhpRepositoryContract;
use Src\Project\Repositories\ProjectPhpRepository;
use Tests\TestCase;

/**
 * @see ProjectPhpRepository
 */
class ProjectPhpRepositoryTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();

		// a Herd with three PHP versions and a project folder next to it
		$this->root = strtr(sys_get_temp_dir(), '\\', '/') . '/devdock-php-' . bin2hex(random_bytes(4));

		foreach (['php83', 'php84', 'php85'] as $folder) {
			File::ensureDirectoryExists($this->root . '/herd/bin/' . $folder);
			File::put($this->root . '/herd/bin/' . $folder . '/php.exe', '');
		}

		File::ensureDirectoryExists($this->root . '/project');
		config(['devdock.herd.bin' => $this->root . '/herd/bin']);
	}

	protected function tearDown(): void
	{
		File::deleteDirectory($this->root);

		parent::tearDown();
	}

	#[Test]
	public function version_for_takes_the_lowest_herd_php_that_meets_the_project(): void
	{
		File::put($this->root . '/project/composer.json', (string) json_encode(['require' => ['php' => '~8.5.0']]));

		$version = resolve(ProjectPhpRepositoryContract::class)->versionFor($this->root . '/project');

		$this->assertSame('8.5', $version);
	}

	#[Test]
	public function version_for_follows_a_locked_package_that_asks_for_more(): void
	{
		File::put($this->root . '/project/composer.json', (string) json_encode(['require' => ['php' => '^8.3']]));
		File::put($this->root . '/project/composer.lock', (string) json_encode([
			'packages' => [['name' => 'vendor/old', 'require' => ['php' => '>= 7']]],
			'packages-dev' => [['name' => 'vendor/new', 'require' => ['php' => '8.4 - 8.6']]],
		]));

		$version = resolve(ProjectPhpRepositoryContract::class)->versionFor($this->root . '/project');

		$this->assertSame('8.4', $version);
	}

	#[Test]
	public function binary_for_falls_back_to_devdocks_own_php_without_a_requirement(): void
	{
		File::put($this->root . '/project/composer.json', (string) json_encode(['require' => ['laravel/framework' => '^13.0']]));

		$binary = resolve(ProjectPhpRepositoryContract::class)->binaryFor($this->root . '/project');

		$this->assertStringNotContainsString($this->root, $binary);
	}

	#[Test]
	public function composer_command_runs_composer_with_the_php_of_the_project(): void
	{
		File::put($this->root . '/project/composer.json', (string) json_encode(['require' => ['php' => '~8.3.0']]));
		File::put($this->root . '/herd/bin/composer.phar', '');

		$command = resolve(ProjectPhpRepositoryContract::class)->composerCommand($this->root . '/project', ['install']);

		$this->assertSame([$this->root . '/herd/bin/php83/php.exe', $this->root . '/herd/bin/composer.phar', 'install'], array_slice($command, 0, 3));
	}
}
