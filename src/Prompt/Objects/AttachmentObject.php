<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A file sent along with a message to the agent, by where it lies under the
 * attachments folder of DevDock.
 */
class AttachmentObject extends ValueObject
{
	private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

	public function __construct(
		public readonly string $name,
		public readonly string $relativePath,
		public readonly int $size,
	) {
		parent::__construct();
	}

	public function isImage(): bool
	{
		return in_array(strtolower(pathinfo($this->name, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
	}

	public function sizeLabel(): string
	{
		return $this->size >= 1048576
			? number_format($this->size / 1048576, 1, ',', '.') . ' MB'
			: max(1, (int) round($this->size / 1024)) . ' kB';
	}
}
