<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class ReviewCommentObjectCollection extends ValueObjectCollection
{
	protected function __construct(ReviewCommentObject ...$reviewCommentObjects)
	{
	}
}
