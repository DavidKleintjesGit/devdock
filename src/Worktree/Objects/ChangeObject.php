<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\ChangeKindEnum;

/**
 * One file a round left behind, with how much of it moved.
 */
class ChangeObject extends ValueObject
{
	public function __construct(
		public readonly string $path,
		public readonly ChangeKindEnum $kind,
		public readonly int $added,
		public readonly int $removed,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			path: (string) $data['path'],
			kind: ChangeKindEnum::fromStatus((string) $data['status']),
			added: (int) ($data['added'] ?? 0),
			removed: (int) ($data['removed'] ?? 0),
		);
	}

	public function name(): string
	{
		return basename($this->path);
	}

	public function folder(): string
	{
		$folder = dirname($this->path);

		return $folder === '.' ? '' : $folder;
	}

	/**
	 * How much moved, as '+12 −3'. A file git has not seen before has no
	 * counts of its own.
	 */
	public function sizeLabel(): ?string
	{
		if ($this->added === 0 && $this->removed === 0) {
			return null;
		}

		return '+' . $this->added . ' −' . $this->removed;
	}
}
