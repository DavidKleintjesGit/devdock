<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What stands in a working copy, read from git, the disk and its .env,
 * without judging any of it.
 */
class WorkingCopyStateObject extends ValueObject
{
	/**
	 * @param  list<string>  $missingCarried  files of the project that the copy lacks
	 */
	public function __construct(
		public readonly ?string $currentBranch,
		public readonly ?string $upstream,
		public readonly string $mainBranch,
		public readonly ?string $operationInProgress,
		public readonly array $missingCarried,
		public readonly bool $hasComposer,
		public readonly bool $hasVendorAutoload,
		public readonly bool $isVendorBorrowed,
		public readonly bool $isComposerLockSame,
		public readonly bool $hasPackageJson,
		public readonly bool $hasNodeModules,
		public readonly bool $isNodeModulesBorrowed,
		public readonly bool $isPackageLockSame,
		public readonly bool $hasPackageLock,
		public readonly bool $hasAssetManifest,
		public readonly bool $hasAppKey,
		public readonly bool $needsStorageLink,
		public readonly bool $hasStorageLink,
		public readonly ?string $appUrl,
	) {
		parent::__construct();
	}
}
