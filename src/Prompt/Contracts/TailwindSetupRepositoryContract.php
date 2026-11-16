<?php

declare(strict_types=1);

namespace Src\Prompt\Contracts;

use Src\Prompt\Objects\TailwindSetupObject;

interface TailwindSetupRepositoryContract
{
	/**
	 * The Tailwind setup of the project in this folder, or null when it has no Tailwind config.
	 */
	public function getFor(string $directory): ?TailwindSetupObject;
}
