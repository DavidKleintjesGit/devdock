<?php

declare(strict_types=1);

namespace Src\Ticket\Policies;

use Src\Ticket\Enums\TicketProjectMatchEnum;
use Src\Ticket\Objects\TicketProjectDecisionObject;
use Src\Ticket\Objects\TicketProjectFactsObject;

/**
 * The rule for the project of a ticket that has no branch or pull request yet.
 * A label you tied to a project yourself wins. Otherwise a label belongs to the
 * project whose name it carries, compared without case, dots and dashes, with
 * and without a trailing "portal": the whole name first, then the first part
 * before a dot or dash, then any part. So PartnerPortal finds partner.consumind,
 * API finds api.consumind before no-time-api, C4U finds c4u-nu and
 * GezondVerzekerd finds energie.gezondverzekerd.
 *
 * When a label fits several projects the shortest name wins, since a second
 * checkout of a project carries a suffix such as -2; two names of the same
 * length leave the ticket without a project.
 */
class TicketProjectPolicy
{
	private const SUFFIX = 'portal';

	public function decide(TicketProjectFactsObject $ticketProjectFactsObject): TicketProjectDecisionObject
	{
		$configured = array_change_key_case($ticketProjectFactsObject->labelProjects);

		foreach ($ticketProjectFactsObject->labels as $label) {
			$project = $configured[strtolower($label)] ?? null;

			if ($project !== null && in_array($project, $ticketProjectFactsObject->projectNames, true)) {
				return new TicketProjectDecisionObject(TicketProjectMatchEnum::CONFIGURED, $project);
			}
		}

		$isAmbiguous = false;

		foreach ($ticketProjectFactsObject->labels as $label) {
			$keys = $this->keysOf($label);

			foreach ([$this->wholeName(...), $this->firstPart(...), $this->parts(...)] as $formsOf) {
				$matches = array_values(array_filter(
					$ticketProjectFactsObject->projectNames,
					fn (string $name): bool => array_intersect($formsOf($name), $keys) !== [],
				));

				if ($matches === []) {
					continue;
				}

				$project = $this->shortest($matches);

				if ($project !== null) {
					return new TicketProjectDecisionObject(TicketProjectMatchEnum::BY_LABEL, $project);
				}

				$isAmbiguous = true;

				break;
			}
		}

		return new TicketProjectDecisionObject(
			$isAmbiguous ? TicketProjectMatchEnum::AMBIGUOUS : TicketProjectMatchEnum::NONE,
			null,
		);
	}

	/**
	 * The forms a label is compared in: as it is, and without a trailing "portal".
	 *
	 * @return list<string>
	 */
	private function keysOf(string $label): array
	{
		$key = $this->squash($label);
		$keys = [$key];

		if (str_ends_with($key, self::SUFFIX) && strlen($key) > strlen(self::SUFFIX)) {
			$keys[] = substr($key, 0, -strlen(self::SUFFIX));
		}

		return $keys;
	}

	/**
	 * @return list<string>
	 */
	private function wholeName(string $name): array
	{
		return [$this->squash($name)];
	}

	/**
	 * @return list<string>
	 */
	private function firstPart(string $name): array
	{
		return array_slice($this->parts($name), 0, 1);
	}

	/**
	 * @return list<string>
	 */
	private function parts(string $name): array
	{
		return array_values(array_map(
			fn (string $part): string => $this->squash($part),
			preg_split('/[.\-_]/', $name) ?: [],
		));
	}

	/**
	 * The one name shorter than all the others, or null when two share the shortest length.
	 *
	 * @param  list<string>  $names
	 */
	private function shortest(array $names): ?string
	{
		usort($names, fn (string $first, string $second): int => strlen($first) <=> strlen($second));

		return count($names) === 1 || strlen($names[0]) < strlen($names[1]) ? $names[0] : null;
	}

	private function squash(string $text): string
	{
		return (string) preg_replace('/[^a-z0-9]/', '', strtolower($text));
	}
}
