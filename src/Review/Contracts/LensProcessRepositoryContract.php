<?php

declare(strict_types=1);

namespace Src\Review\Contracts;

interface LensProcessRepositoryContract
{
	/**
	 * Puts as many workers on the lens queue as there are lenses waiting. Each
	 * one takes a single lens and stops, so nothing keeps running once the
	 * round is over.
	 */
	public function startWorkers(int $count): void;
}
