<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Enums\CheckStateEnum;
use Src\Worktree\Enums\ProjectKindEnum;
use Src\Worktree\Enums\SetupCheckEnum;
use Src\Worktree\Objects\ReadinessObject;
use Src\Worktree\Objects\SetupCheckObject;

/**
 * The checklist as a card shows it: read lightly and kept for a minute, so
 * every tab of a card can show where it stands without reading it again. The
 * gate never uses this; it reads the list fresh.
 *
 * @method static ReadinessObject execute(?string $directory)
 */
class GetReadinessAction extends Command
{
	private const KEY = 'setup.readiness.';

	private const SECONDS = 60;

	/**
	 * The checklist stores as plain text, since the cache refuses objects; reading
	 * it back allows only the classes a checklist is made of.
	 */
	private const ALLOWED_CLASSES = [
		ReadinessObject::class,
		SetupCheckObject::class,
		SetupCheckEnum::class,
		CheckStateEnum::class,
		ProjectKindEnum::class,
		CarbonImmutable::class,
	];

	public function __construct(
		public readonly ?string $directory,
	) {}

	public static function forget(?string $directory): void
	{
		Cache::forget(self::KEY . md5(CheckReadinessAction::normalize((string) $directory)));
	}

	public function handle(): ReadinessObject
	{
		$key = self::KEY . md5(CheckReadinessAction::normalize((string) $this->directory));
		$kept = Cache::get($key);
		$readinessObject = is_string($kept) ? @unserialize($kept, ['allowed_classes' => self::ALLOWED_CLASSES]) : null;

		if ($readinessObject instanceof ReadinessObject) {
			return $readinessObject;
		}

		$readinessObject = CheckReadinessAction::execute($this->directory);
		Cache::put($key, serialize($readinessObject), self::SECONDS);

		return $readinessObject;
	}
}
