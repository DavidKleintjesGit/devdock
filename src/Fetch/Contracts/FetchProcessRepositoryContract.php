<?php

declare(strict_types=1);

namespace Src\Fetch\Contracts;

use Src\Fetch\Objects\FetchRunId;

interface FetchProcessRepositoryContract
{
	/**
	 * Puts the fetching away as a separate process, so the page does not hang
	 * while the repositories are walked.
	 */
	public function startDetached(FetchRunId $fetchRunId): void;

	/**
	 * Which php executable the separate process uses; that is readable in the
	 * settings.
	 */
	public function binaryInUse(): string;
}
