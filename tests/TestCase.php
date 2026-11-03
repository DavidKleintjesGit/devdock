<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
	use RefreshDatabase;

	private string $storagePath;

	protected function setUp(): void
	{
		parent::setUp();

		// logs, prompt instructions and round folders land in a storage of the test's own,
		// never in the storage of the DevDock in use
		$this->storagePath = sys_get_temp_dir() . '/devdock-tests/storage-' . Str::random(12);
		File::ensureDirectoryExists($this->storagePath . '/app');
		File::ensureDirectoryExists($this->storagePath . '/framework/cache');
		$this->app->useStoragePath($this->storagePath);

		$this->withoutVite();
	}

	protected function tearDown(): void
	{
		File::deleteDirectory($this->storagePath);

		parent::tearDown();
	}
}
