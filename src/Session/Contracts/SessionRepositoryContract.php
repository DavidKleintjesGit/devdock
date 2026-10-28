<?php

declare(strict_types=1);

namespace Src\Session\Contracts;

use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionId;
use Src\Session\Objects\SessionObject;
use Src\Session\Objects\SessionObjectCollection;
use Src\Session\Objects\SessionThresholdObject;

interface SessionRepositoryContract
{
	public function create(SessionObject $sessionObject, SessionThresholdObject $thresholds): SessionEntity;

	public function getAll(SessionThresholdObject $thresholds): SessionEntityCollection;

	public function getById(SessionId $sessionId, SessionThresholdObject $thresholds): ?SessionEntity;

	/**
	 * Brings the stored sessions in line with the files the scan found.
	 */
	public function replaceAll(
		SessionObjectCollection $sessionObjects,
		SessionThresholdObject $thresholds,
	): SessionEntityCollection;

	public function updateTitle(SessionId $sessionId, string $title, string $fingerprint): void;

	public function delete(SessionId $sessionId): void;
}
