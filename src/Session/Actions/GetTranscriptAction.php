<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Src\Packages\Cqrs\Command;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Objects\SessionId;
use Src\Session\Objects\SessionThresholdObject;
use Src\Session\Objects\TranscriptTurnObjectCollection;

/**
 * The first turns of a session file. The board reads the file and writes
 * nothing into it.
 *
 * @method static TranscriptTurnObjectCollection execute(SessionId $sessionId)
 */
class GetTranscriptAction extends Command
{
	private const TURNS = 20;

	public function __construct(
		public readonly SessionId $sessionId,
	) {
	}

	public function handle(
		SessionRepositoryContract $sessionRepository,
		SessionFileRepositoryContract $sessionFileRepository,
	): TranscriptTurnObjectCollection {
		$thresholds = SessionThresholdObject::from(config('devdock.sessions'));
		$sessionEntity = $sessionRepository->getById($this->sessionId, $thresholds);

		if ($sessionEntity === null) {
			return TranscriptTurnObjectCollection::fromArray([]);
		}

		return $sessionFileRepository->getTurns($sessionEntity->relativePath(), self::TURNS);
	}
}
