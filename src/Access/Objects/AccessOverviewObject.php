<?php

declare(strict_types=1);

namespace Src\Access\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * Everything the Access page shows: the policy of every role, the servers
 * whose writing tools are denied, and the git commands that pass.
 */
class AccessOverviewObject extends ValueObject
{
	/**
	 * @param  list<RoleAccessObject>  $roleAccesses
	 * @param  list<string>  $servers
	 * @param  list<string>  $writingTools
	 * @param  list<string>  $agentOperations
	 * @param  list<string>  $gitReading
	 * @param  list<string>  $gitListing
	 */
	public function __construct(
		public readonly array $roleAccesses,
		public readonly array $servers,
		public readonly array $writingTools,
		public readonly array $agentOperations,
		public readonly array $gitReading,
		public readonly array $gitListing,
		public readonly string $gitRefusal,
	) {
		parent::__construct();
	}
}
