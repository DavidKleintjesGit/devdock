<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class PullRequestObjectCollection extends ValueObjectCollection
{
	protected function __construct(PullRequestObject ...$pullRequestObjects)
	{
	}
}
