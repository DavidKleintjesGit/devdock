<?php

declare(strict_types=1);

namespace Src\Fetch\Repositories;

use Src\Fetch\Contracts\FetchProcessRepositoryContract;
use Src\Fetch\Objects\FetchRunId;

class FetchProcessRepository implements FetchProcessRepositoryContract
{
	public function startDetached(FetchRunId $fetchRunId): void
	{
		// over the web the working folder is public/, so artisan gets its full path
		$command = sprintf(
			'%s %s devdock:fetch --run=%d',
			escapeshellarg($this->binaryInUse()),
			escapeshellarg(base_path('artisan')),
			(int) $fetchRunId->id,
		);

		$handle = popen($this->detached($command), 'r');

		if (is_resource($handle)) {
			pclose($handle);
		}
	}

	/**
	 * Over the web php runs as fpm or cgi; the command needs the separate php
	 * executable that sits next to it.
	 */
	public function binaryInUse(): string
	{
		if (filled(config('devdock.php_binary'))) {
			return (string) config('devdock.php_binary');
		}

		if (PHP_SAPI === 'cli') {
			return PHP_BINARY;
		}

		$name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
		$sibling = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $name;

		return is_file($sibling) ? $sibling : 'php';
	}

	/**
	 * Cutting loose from this request: on Windows through start, elsewhere by
	 * sending the process to the background.
	 */
	private function detached(string $command): string
	{
		if (PHP_OS_FAMILY === 'Windows') {
			return 'start /B "" ' . $command;
		}

		return $command . ' > /dev/null 2>&1 &';
	}
}
