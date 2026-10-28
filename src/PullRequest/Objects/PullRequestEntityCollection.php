<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class PullRequestEntityCollection extends ValueObjectCollection
{
	protected function __construct(PullRequestEntity ...$pullRequestEntities)
	{
	}
}
