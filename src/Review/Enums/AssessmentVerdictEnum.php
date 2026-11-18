<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * What the agent made of one finding when it assessed a round: whether the
 * lens is right, doubtful, wrong, or already answered by the code.
 */
enum AssessmentVerdictEnum: string
{
	case AGREE = 'klopt';
	case DOUBT = 'twijfel';
	case DISAGREE = 'oneens';
	case RESOLVED = 'opgelost';

	public function label(): string
	{
		return __(match ($this) {
			self::AGREE => 'Klopt',
			self::DOUBT => 'Twijfel',
			self::DISAGREE => 'Oneens',
			self::RESOLVED => 'Al opgelost',
		});
	}

	/**
	 * The choice the list starts on for this verdict; you change it with one click.
	 */
	public function suggestedChoice(): FindingChoiceEnum
	{
		return match ($this) {
			self::AGREE => FindingChoiceEnum::FIX,
			self::DOUBT, self::DISAGREE => FindingChoiceEnum::IGNORE,
			self::RESOLVED => FindingChoiceEnum::RESOLVED,
		};
	}
}
