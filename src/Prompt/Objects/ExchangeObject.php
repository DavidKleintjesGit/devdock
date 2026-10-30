<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One exchange with the agent: what you sent and what came back. The answer
 * is split in two — everything the agent wrote in its own words, and the
 * closing report that was fenced off at the end of it.
 */
class ExchangeObject extends ValueObject
{
	public function __construct(
		public readonly PromptRunEntity $run,
		public readonly ?string $body,
		public readonly ?ReportObject $report,
	) {
		parent::__construct();
	}
}
