<?php

declare(strict_types=1);

namespace Src\Session\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Src\Project\Objects\ProjectEntity;
use Src\Project\Objects\ProjectEntityCollection;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionObject;
use Src\Session\Objects\SessionObjectCollection;
use Src\Session\Objects\TranscriptTurnObject;
use Src\Session\Objects\TranscriptTurnObjectCollection;

class SessionFileRepository implements SessionFileRepositoryContract
{
	public function getAll(
		ProjectEntityCollection $projectEntities,
		SessionEntityCollection $storedSessionEntities,
	): SessionObjectCollection {
		$root = $this->root();

		if (! is_dir($root)) {
			return SessionObjectCollection::fromArray([]);
		}

		$projectNames = $this->projectNamesByFolder($projectEntities);
		$stored = $this->storedByUuid($storedSessionEntities);
		$sessionObjects = [];

		foreach (scandir($root) ?: [] as $folder) {
			if ($folder === '.' || $folder === '..' || ! is_dir($root . '/' . $folder)) {
				continue;
			}

			foreach (glob($root . '/' . $folder . '/*.jsonl') ?: [] as $file) {
				$sessionObjects[] = $this->sessionObjectOf(
					$file,
					$folder,
					$projectNames[$folder] ?? null,
					$stored,
				);
			}
		}

		return SessionObjectCollection::fromArray($sessionObjects);
	}

	public function appendTitle(string $relativePath, string $uuid, string $title): ?string
	{
		$path = $this->root() . '/' . $relativePath;

		if (! is_file($path)) {
			return null;
		}

		// Claude Code keeps the name as a separate record at the end of the session
		// file and reads the last one, so renaming is appending a line
		$record = json_encode([
			'type' => 'custom-title',
			'customTitle' => $title,
			'sessionId' => $uuid,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (@file_put_contents($path, $record . "\n", FILE_APPEND) === false) {
			return null;
		}

		return $this->fingerprintOf($path);
	}

	public function delete(string $relativePath): bool
	{
		$path = $this->root() . '/' . $relativePath;

		return ! is_file($path) || @unlink($path);
	}

	public function getTurns(string $relativePath, int $limit): TranscriptTurnObjectCollection
	{
		$path = $this->root() . '/' . $relativePath;
		$handle = is_file($path) ? fopen($path, 'r') : false;

		if ($handle === false) {
			return TranscriptTurnObjectCollection::fromArray([]);
		}

		$transcriptTurnObjects = [];

		while (count($transcriptTurnObjects) < $limit && ($line = fgets($handle)) !== false) {
			if (! str_contains($line, '"type":"user"')) {
				continue;
			}

			$record = json_decode($line, true);
			$text = $this->textOf($record);

			if ($text === null) {
				continue;
			}

			$transcriptTurnObjects[] = TranscriptTurnObject::from([
				'who' => 'jij',
				'text' => Str::of($text)->squish()->limit(400)->value(),
				'at' => isset($record['timestamp']) ? CarbonImmutable::parse($record['timestamp'])->format('H:i') : '',
			]);
		}

		fclose($handle);

		return TranscriptTurnObjectCollection::fromArray($transcriptTurnObjects);
	}

	private function root(): string
	{
		return (string) config('devdock.paths.sessions');
	}

	/**
	 * @param array<string, SessionEntity> $stored
	 */
	private function sessionObjectOf(string $file, string $folder, ?string $project, array $stored): SessionObject
	{
		$uuid = basename($file, '.jsonl');
		$fingerprint = $this->fingerprintOf($file);
		$known = $stored[$uuid] ?? null;

		$contents = $known !== null && $this->fingerprintMatches($known, $fingerprint)
			? ['title' => $known->title, 'branch' => $known->branch, 'prompt_count' => $known->promptCount]
			: $this->contentsOf($file);

		return SessionObject::from($contents + [
			'uuid' => $uuid,
			'folder' => $folder,
			'project' => $project,
			'bytes' => (int) filesize($file),
			'last_active_at' => CarbonImmutable::createFromTimestamp((int) filemtime($file)),
			'fingerprint' => $fingerprint,
		]);
	}

	/**
	 * The fingerprint is size and change moment together; when nothing about the
	 * file changed, it does not have to be read again.
	 */
	private function fingerprintOf(string $path): string
	{
		clearstatcache(true, $path);

		return filesize($path) . ':' . filemtime($path);
	}

	private function fingerprintMatches(SessionEntity $sessionEntity, string $fingerprint): bool
	{
		return $sessionEntity->fingerprint === $fingerprint;
	}

	/**
	 * The name, the branch and the number of prompts in a session file.
	 *
	 * @return array<string, int|string|null>
	 */
	private function contentsOf(string $file): array
	{
		$handle = fopen($file, 'r');

		if ($handle === false) {
			return ['title' => null, 'branch' => null, 'prompt_count' => 0];
		}

		$title = null;
		$branch = null;
		$promptCount = 0;

		while (($line = fgets($handle)) !== false) {
			if (str_contains($line, '"custom-title"')) {
				$record = json_decode($line, true);
				$title = $record['customTitle'] ?? $title;

				continue;
			}

			if (! str_contains($line, '"type":"user"')) {
				continue;
			}

			$record = json_decode($line, true);

			if (($record['type'] ?? null) !== 'user') {
				continue;
			}

			$branch ??= $record['gitBranch'] ?? null;

			if ($this->isPrompt($record)) {
				$promptCount++;
			}
		}

		fclose($handle);

		return [
			'title' => $title,
			'branch' => $branch ?: null,
			'prompt_count' => $promptCount,
		];
	}

	/**
	 * Whether this line is a turn of yours. A tool result comes in as a user
	 * line, but is not a prompt.
	 */
	private function isPrompt(array $record): bool
	{
		if (($record['isMeta'] ?? false) === true) {
			return false;
		}

		$content = $record['message']['content'] ?? null;

		if (is_string($content)) {
			return true;
		}

		if (! is_array($content)) {
			return false;
		}

		foreach ($content as $block) {
			if (($block['type'] ?? null) === 'tool_result') {
				return false;
			}
		}

		return true;
	}

	private function textOf(?array $record): ?string
	{
		if (($record['type'] ?? null) !== 'user' || ($record['isMeta'] ?? false) === true) {
			return null;
		}

		$content = $record['message']['content'] ?? null;

		if (is_string($content)) {
			return $content;
		}

		if (! is_array($content)) {
			return null;
		}

		foreach ($content as $block) {
			if (($block['type'] ?? null) === 'tool_result') {
				return null;
			}

			if (($block['type'] ?? null) === 'text') {
				return $block['text'] ?? null;
			}
		}

		return null;
	}

	/**
	 * The folder name per session folder. Claude Code makes a folder per project
	 * whose name is the path with dashes in the place of every other character.
	 *
	 * @return array<string, string>
	 */
	private function projectNamesByFolder(ProjectEntityCollection $projectEntities): array
	{
		$names = [];

		foreach ($projectEntities as $projectEntity) {
			$folder = preg_replace('/[^A-Za-z0-9]/', '-', $projectEntity->path);
			$names[$folder] = $projectEntity->name;
		}

		return $names;
	}

	/**
	 * @return array<string, SessionEntity>
	 */
	private function storedByUuid(SessionEntityCollection $sessionEntities): array
	{
		$stored = [];

		foreach ($sessionEntities as $sessionEntity) {
			$stored[$sessionEntity->uuid] = $sessionEntity;
		}

		return $stored;
	}
}
