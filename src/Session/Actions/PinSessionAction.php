<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Src\Packages\Cqrs\Command;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Objects\SessionId;

/**
 * Keeps a session out of every clean-up, or lets it go again.
 *
 * @method static void execute(SessionId $sessionId, bool $isPinned)
 */
class PinSessionAction extends Command
{
	public function __construct(
		public readonly SessionId $sessionId,
		public readonly bool $isPinned,
	) {}

	public function handle(SessionRepositoryContract $sessionRepository): void
	{
		$sessionRepository->pin($this->sessionId, $this->isPinned);
	}
}
