<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One exchange with the agent: what you sent and what came back. The answer
 * is kept whole, in the order it was written: passages of text and the
 * screens drawn between them. The closing report and the answers it offers
 * are lifted out, so they can carry buttons.
 */
class ExchangeObject extends ValueObject
{
	/**
	 * @param  array<int, string|MockupObject>  $passages  rendered html or a drawn screen
	 * @param  array<int, string>  $choices
	 */
	public function __construct(
		public readonly PromptRunEntity $run,
		public readonly array $passages,
		public readonly ?ReportObject $report,
		public readonly array $choices,
		public readonly ?TranscriptObject $transcript,
	) {
		parent::__construct();
	}
}
