<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Src\Packages\Cqrs\Command;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Objects\SessionId;
use Src\Session\Objects\SessionThresholdObject;

/**
 * Wipes the session file and the row with it. The conversation is gone
 * afterwards and `claude --resume` no longer works for this session.
 *
 * @method static bool execute(SessionId $sessionId)
 */
class DeleteSessionAction extends Command
{
	public function __construct(
		public readonly SessionId $sessionId,
	) {
	}

	public function handle(
		SessionRepositoryContract $sessionRepository,
		SessionFileRepositoryContract $sessionFileRepository,
	): bool {
		$thresholds = SessionThresholdObject::from(config('devdock.sessions'));
		$sessionEntity = $sessionRepository->getById($this->sessionId, $thresholds);

		if ($sessionEntity === null) {
			return false;
		}

		if (! $sessionFileRepository->delete($sessionEntity->relativePath())) {
			return false;
		}

		$sessionRepository->delete($sessionEntity->sessionId);

		return true;
	}
}
