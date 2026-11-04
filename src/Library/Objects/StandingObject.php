<?php

declare(strict_types=1);

namespace Src\Library\Objects;

use Src\Library\Enums\StandingEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * How one library item stands in one project.
 */
class StandingObject extends ValueObject
{
	public function __construct(
		public readonly string $project,
		public readonly LibraryItemEntity $item,
		public readonly StandingEnum $standing,
		public readonly ?LibraryVersionEntity $onVersion,
		public readonly string $path,
		public readonly ?int $lines,
	) {
		parent::__construct();
	}

	public function isCurrent(): bool
	{
		return $this->standing === StandingEnum::EQUAL;
	}

	public function isModified(): bool
	{
		return $this->standing === StandingEnum::MODIFIED;
	}

	public function isPresent(): bool
	{
		return $this->standing !== StandingEnum::MISSING;
	}

	/**
	 * The line under the file name: which version it is on, or why it fits
	 * none.
	 */
	public function detail(): string
	{
		$latest = $this->item->latest();

		return match ($this->standing) {
			StandingEnum::EQUAL => __(':version · de nieuwste', ['version' => $latest?->label()]),
			StandingEnum::BEHIND => __('op :version van :date · de nieuwste is :latest', [
				'version' => $this->onVersion?->label(),
				'date' => $this->onVersion?->createdAt?->toDisplayZone()->translatedFormat('j F'),
				'latest' => $latest?->label(),
			]),
			StandingEnum::MODIFIED => __('past bij geen enkele versie: hier zelf veranderd'),
			StandingEnum::MISSING => __('staat niet in het project'),
		};
	}
}
