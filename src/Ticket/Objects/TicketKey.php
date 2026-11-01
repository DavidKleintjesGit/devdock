<?php

declare(strict_types=1);

namespace Src\Ticket\Objects;

use Illuminate\Support\Str;
use Src\Ticket\Models\Ticket as TicketEloquent;

/**
 * Finds a ticket number in a branch, a title or a session name. Which
 * prefixes count comes from the tickets Jira gave back, so every Jira
 * project you work in is recognised and nothing is fixed in the code.
 */
class TicketKey
{
	/**
	 * Used until the first tickets are fetched: any Jira-like project key.
	 */
	private const ANY_PREFIX = '[A-Z][A-Z0-9]{1,9}';

	private static ?string $pattern = null;

	public static function find(?string $text): ?string
	{
		if ($text === null || $text === '') {
			return null;
		}

		return preg_match(self::pattern(), $text, $found) === 1 ? strtoupper($found[0]) : null;
	}

	/**
	 * Forgets the prefixes, for when a fetch brought in tickets of a new
	 * project.
	 */
	public static function forget(): void
	{
		self::$pattern = null;
	}

	private static function pattern(): string
	{
		return self::$pattern ??= (function (): string {
			$prefixes = TicketEloquent::query()
				->pluck('key')
				->map(fn (mixed $key) => preg_quote(Str::before((string) $key, '-'), '/'))
				->unique()
				->values()
				->all();

			$prefix = $prefixes === [] ? self::ANY_PREFIX : '(?:' . implode('|', $prefixes) . ')';

			return '/(?<![A-Za-z0-9])' . $prefix . '-\d+(?!\d)/i';
		})();
	}
}
