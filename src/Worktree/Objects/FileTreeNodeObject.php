<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One node in the tree of changed files: a folder with what is in it, or a
 * file with its change. A folder that holds only one folder carries both
 * names, the way Bitbucket shows them.
 */
class FileTreeNodeObject extends ValueObject
{
	/**
	 * @param  array<int, FileTreeNodeObject>  $children
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $path,
		public readonly ?ChangeObject $change,
		public readonly array $children,
	) {
		parent::__construct();
	}

	public function isFolder(): bool
	{
		return $this->change === null;
	}

	/**
	 * How many files stand somewhere below this folder.
	 */
	public function fileCount(): int
	{
		return $this->isFolder()
			? array_sum(array_map(fn (FileTreeNodeObject $child) => $child->fileCount(), $this->children))
			: 1;
	}

	/**
	 * The files below this node in the order the tree shows them.
	 *
	 * @return array<int, string>
	 */
	public function filePaths(): array
	{
		return $this->isFolder()
			? array_merge([], ...array_map(fn (FileTreeNodeObject $child) => $child->filePaths(), $this->children))
			: [$this->path];
	}
}
