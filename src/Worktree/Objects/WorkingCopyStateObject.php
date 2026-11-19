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
	 * @param  list<string>  $missingExcludes  lines for DevDock's own files the exclude of the repository lacks
	 * @param  list<string>  $missingVendorPackages  package folders of the project a borrowed vendor lacks
	 * @param  list<string>  $staleVendorPackages  packages composer.lock asks for that vendor holds at another version or not at all
	 * @param  list<string>  $linkedScriptPackages  packages with a script that a borrowed vendor still reaches partly in the checkout
	 * @param  ?bool  $hasTestAppKey  whether the .env.testing holds an APP_KEY, or null when the copy has no .env.testing
	 */
	public function __construct(
		public readonly ?string $currentBranch,
		public readonly ?string $upstream,
		public readonly string $mainBranch,
		public readonly ?string $operationInProgress,
		public readonly array $missingCarried,
		public readonly array $missingExcludes,
		public readonly bool $hasComposer,
		public readonly bool $hasVendorAutoload,
		public readonly bool $isVendorBorrowed,
		public readonly array $missingVendorPackages,
		public readonly array $staleVendorPackages,
		public readonly bool $isComposerLockSame,
		public readonly bool $hasPackageJson,
		public readonly bool $hasNodeModules,
		public readonly bool $isNodeModulesBorrowed,
		public readonly bool $isPackageLockSame,
		public readonly bool $hasPackageLock,
		public readonly bool $hasAssetManifest,
		public readonly bool $hasAppKey,
		public readonly ?bool $hasTestAppKey,
		public readonly bool $needsStorageLink,
		public readonly bool $hasStorageLink,
		public readonly ?string $appUrl,
		public readonly array $linkedScriptPackages = [],
	) {
		parent::__construct();
	}
}
