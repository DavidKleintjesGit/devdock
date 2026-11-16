<?php

declare(strict_types=1);

namespace Src\Ticket\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides the project of a ticket that has no branch or pull request yet:
 * its Jira labels, the projects on disk, and the labels you tied to a project
 * yourself.
 */
final class TicketProjectFactsObject extends ValueObject
{
	/**
	 * @param  list<string>  $labels
	 * @param  list<string>  $projectNames
	 * @param  array<string, string>  $labelProjects  label => project name
	 */
	public function __construct(
		public readonly array $labels,
		public readonly array $projectNames,
		public readonly array $labelProjects,
	) {
		parent::__construct();
	}
}
