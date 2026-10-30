<?php

declare(strict_types=1);

namespace Src\Task\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * The Claude session a task is worked in. The key is ours: we hand it out
 * before the first prompt leaves, so the agent is findable from the start.
 *
 * Claude keeps a session's transcript in a folder named after the directory it
 * ran in, so the directory is part of reaching it again.
 */
class TaskAgentEntity extends ValueObject
{
	/**
	 * An agent is someone you talk to, so it carries a name and not only a
	 * key. The names stay clear of the people who actually work here.
	 */
	public const NAMES = [
		'Ada', 'Bram', 'Cato', 'Dex', 'Elif', 'Finn', 'Gwen', 'Hugo', 'Iris',
		'Joris', 'Kiva', 'Lotte', 'Milan', 'Nova', 'Olle', 'Pim', 'Quinn',
		'Roos', 'Stijn', 'Tess', 'Uma', 'Vera', 'Wout', 'Xan', 'Yara', 'Zeno',
	];

	public function __construct(
		public readonly string $sessionUuid,
		public readonly string $name,
		public readonly ?string $directory,
		public readonly bool $isActive,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $createdAt,
	) {
		parent::__construct();
	}

	/**
	 * Whether a round has run on this key. Until one has, Claude is told to
	 * take the key; afterwards it is resumed.
	 */
	public function hasStarted(): bool
	{
		return $this->startedAt !== null;
	}

	/**
	 * A name for an agent that was handed out before names existed. The key
	 * decides it, so it stays the same every time.
	 */
	public static function nameFor(string $sessionUuid): string
	{
		return self::NAMES[hexdec(substr(md5($sessionUuid), 0, 6)) % count(self::NAMES)];
	}

	public function shortKey(): string
	{
		return substr($this->sessionUuid, 0, 8) . '…' . substr($this->sessionUuid, -4);
	}
}
