<?php

declare(strict_types=1);

namespace Src\Session\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Src\Project\Objects\ProjectEntityCollection;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionObject;
use Src\Session\Objects\SessionObjectCollection;
use Src\Session\Objects\TranscriptTurnObject;
use Src\Session\Objects\TranscriptTurnObjectCollection;
use Src\Worktree\Objects\WorktreeEntityCollection;

class SessionFileRepository implements SessionFileRepositoryContract
{
	public function getAll(
		ProjectEntityCollection $projectEntities,
		WorktreeEntityCollection $worktreeEntities,
		SessionEntityCollection $storedSessionEntities,
	): SessionObjectCollection {
		$root = $this->root();

		if (! is_dir($root)) {
			return SessionObjectCollection::fromArray([]);
		}

		$projectNames = $this->projectNamesByFolder($projectEntities, $worktreeEntities);
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

	public function countFor(string $workingDirectory): int
	{
		return count(glob($this->sessionFolderOf($workingDirectory) . '/*.jsonl') ?: []);
	}

	public function deleteFor(string $workingDirectory): int
	{
		$count = $this->countFor($workingDirectory);
		$folder = $this->sessionFolderOf($workingDirectory);

		if (is_dir($folder)) {
			File::deleteDirectory($folder);
		}

		return $count;
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
				'at' => isset($record['timestamp']) ? CarbonImmutable::parse($record['timestamp'])->toDisplayZone()->format('H:i') : '',
			]);
		}

		fclose($handle);

		return TranscriptTurnObjectCollection::fromArray($transcriptTurnObjects);
	}

	/**
	 * Where Claude Code keeps the sessions of a folder on disk.
	 */
	private function sessionFolderOf(string $workingDirectory): string
	{
		return rtrim($this->root(), '/\\') . '/' . $this->folderFor($workingDirectory);
	}

	private function root(): string
	{
		return (string) config('devdock.paths.sessions');
	}

	/**
	 * @param  array<string, SessionEntity>  $stored
	 */
	private function sessionObjectOf(string $file, string $folder, ?string $project, array $stored): SessionObject
	{
		$uuid = basename($file, '.jsonl');
		$fingerprint = $this->fingerprintOf($file);
		$known = $stored[$uuid] ?? null;

		$contents = $known !== null && $this->fingerprintMatches($known, $fingerprint)
			? [
				'title' => $known->title,
				'branch' => $known->branch,
				'prompt_count' => $known->promptCount,
				'cwd' => $known->cwd,
				'first_active_at' => $known->firstActiveAt,
				'active_days' => $known->activeDays,
				'has_commit' => $known->hasCommit,
				'is_interrupted' => $known->isInterrupted,
				'is_unanswered' => $known->isUnanswered,
			]
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
		// a row from before the traits were read has to be read once more
		return $sessionEntity->fingerprint === $fingerprint && $sessionEntity->firstActiveAt !== null;
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
		$cwd = null;
		$promptCount = 0;
		$firstAt = null;
		$days = [];
		$hasCommit = false;
		$lastPrompt = null;
		$answered = true;

		while (($line = fgets($handle)) !== false) {
			if (str_contains($line, '"custom-title"')) {
				$record = json_decode($line, true);
				$title = $record['customTitle'] ?? $title;

				continue;
			}

			$isUser = str_contains($line, '"type":"user"');
			$isAssistant = str_contains($line, '"type":"assistant"');

			if (! $isUser && ! $isAssistant) {
				continue;
			}

			$record = json_decode($line, true);

			if (isset($record['timestamp'])) {
				$moment = CarbonImmutable::parse($record['timestamp']);
				$firstAt ??= $moment;
				$days[$moment->toDisplayZone()->toDateString()] = true;
			}

			$cwd ??= $record['cwd'] ?? null;

			if (($record['type'] ?? null) === 'assistant') {
				$answered = true;
				$hasCommit = $hasCommit || (str_contains($line, '"tool_use"') && str_contains($line, 'git commit'));

				continue;
			}

			if (($record['type'] ?? null) !== 'user') {
				continue;
			}

			$branch ??= $record['gitBranch'] ?? null;

			if ($this->isPrompt($record)) {
				$promptCount++;
				$lastPrompt = $this->textOf($record);
				$answered = false;
			}
		}

		fclose($handle);

		// Claude Code writes this line itself when you break off a turn
		$isInterrupted = str_starts_with(trim((string) $lastPrompt), '[Request interrupted');

		return [
			'title' => $title,
			'branch' => $branch ?: null,
			'prompt_count' => $promptCount,
			'cwd' => $cwd === null ? null : str_replace('\\', '/', $cwd),
			'first_active_at' => $firstAt,
			'active_days' => max(1, count($days)),
			'has_commit' => $hasCommit,
			'is_interrupted' => $isInterrupted,
			'is_unanswered' => ! $answered && ! $isInterrupted,
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
	private function projectNamesByFolder(
		ProjectEntityCollection $projectEntities,
		WorktreeEntityCollection $worktreeEntities,
	): array {
		$names = [];

		foreach ($projectEntities as $projectEntity) {
			$names[$this->folderFor($projectEntity->path)] = $projectEntity->name;
		}

		// a working copy is the same project, so its sessions belong with it
		foreach ($worktreeEntities as $worktreeEntity) {
			$names[$this->folderFor($worktreeEntity->path)] = $worktreeEntity->project;
		}

		return $names;
	}

	private function folderFor(string $path): string
	{
		return (string) preg_replace('/[^A-Za-z0-9]/', '-', $path);
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
