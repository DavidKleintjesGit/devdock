<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Src\Packages\Cqrs\Command;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Objects\SessionId;
use Src\Session\Objects\SessionThresholdObject;

/**
 * Gives a session another name, in the session file itself and in storage.
 *
 * @method static bool execute(SessionId $sessionId, string $title)
 */
class RenameSessionAction extends Command
{
	public function __construct(
		public readonly SessionId $sessionId,
		public readonly string $title,
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

		$fingerprint = $sessionFileRepository->appendTitle(
			$sessionEntity->relativePath(),
			$sessionEntity->uuid,
			$this->title,
		);

		if ($fingerprint === null) {
			return false;
		}

		$sessionRepository->updateTitle($sessionEntity->sessionId, $this->title, $fingerprint);

		return true;
	}
}
