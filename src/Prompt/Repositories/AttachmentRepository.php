<?php

declare(strict_types=1);

namespace Src\Prompt\Repositories;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Src\Prompt\Contracts\AttachmentRepositoryContract;
use Src\Prompt\Objects\AttachmentObject;

class AttachmentRepository implements AttachmentRepositoryContract
{
	public const MAX_BYTES = 20 * 1024 * 1024;

	private const PENDING_FOLDER = 'pending';

	private const PENDING_HOURS = 24;

	public function storeChunk(string $upload, int $chunk, int $chunks, string $name, string $content): ?AttachmentObject
	{
		if (preg_match('/^[a-f0-9]{16,64}$/', $upload) !== 1 || $chunk < 0 || $chunk >= $chunks) {
			throw new RuntimeException(__('Dit stuk van de upload klopt niet.'));
		}

		$folder = $this->root() . '/' . self::PENDING_FOLDER . '/' . $upload;

		if ($chunk === 0) {
			$this->prunePending();
			File::ensureDirectoryExists($folder);
			File::put($folder . '/name', $this->safeName($name));
			File::put($folder . '/data', '');
		}

		if (! is_file($folder . '/data')) {
			throw new RuntimeException(__('Het begin van deze upload ontbreekt. Sleep het bestand nog een keer.'));
		}

		File::append($folder . '/data', $content);
		clearstatcache(true, $folder . '/data');

		if (filesize($folder . '/data') > self::MAX_BYTES) {
			File::deleteDirectory($folder);

			throw new RuntimeException(__('Een bijlage mag hoogstens 20 MB zijn.'));
		}

		return $chunk === $chunks - 1 ? $this->getPending($upload) : null;
	}

	public function getPending(string $upload): ?AttachmentObject
	{
		$folder = $this->root() . '/' . self::PENDING_FOLDER . '/' . $upload;

		if (preg_match('/^[a-f0-9]{16,64}$/', $upload) !== 1 || ! is_file($folder . '/data') || ! is_file($folder . '/name')) {
			return null;
		}

		return new AttachmentObject(
			name: (string) file_get_contents($folder . '/name'),
			relativePath: self::PENDING_FOLDER . '/' . $upload . '/data',
			size: (int) filesize($folder . '/data'),
		);
	}

	public function attach(string $card, array $uploads): array
	{
		$folder = $this->root() . '/' . $this->folderName($card);
		$paths = [];

		foreach ($uploads as $upload) {
			$attachmentObject = $this->getPending($upload);

			if ($attachmentObject === null) {
				continue;
			}

			File::ensureDirectoryExists($folder);
			$path = $folder . '/' . $upload . '-' . $attachmentObject->name;
			File::move($this->root() . '/' . $attachmentObject->relativePath, $path);
			File::deleteDirectory($this->root() . '/' . self::PENDING_FOLDER . '/' . $upload);
			$paths[] = $path;
		}

		return $paths;
	}

	public function fromPath(string $path): ?AttachmentObject
	{
		$normalized = strtr($path, '\\', '/');
		$root = $this->root() . '/';

		if (! str_starts_with(strtolower($normalized), strtolower($root))) {
			return null;
		}

		$relativePath = substr($normalized, strlen($root));

		return new AttachmentObject(
			// the upload key stands in front of the name, so two files with one name do not collide
			name: (string) preg_replace('/^[a-f0-9]{16,64}-/', '', basename($relativePath)),
			relativePath: $relativePath,
			size: is_file($normalized) ? (int) filesize($normalized) : 0,
		);
	}

	public function fileAt(string $relativePath): ?string
	{
		$root = realpath($this->root());
		$file = $root === false ? false : realpath($root . '/' . $relativePath);

		return $file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file) ? $file : null;
	}

	public function folderFor(string $card): ?string
	{
		$folder = $this->root() . '/' . $this->folderName($card);

		return is_dir($folder) ? $folder : null;
	}

	public function deleteFor(string $card): void
	{
		File::deleteDirectory($this->root() . '/' . $this->folderName($card));
	}

	private function prunePending(): void
	{
		foreach (glob($this->root() . '/' . self::PENDING_FOLDER . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
			if (filemtime($folder) < time() - self::PENDING_HOURS * 3600) {
				File::deleteDirectory($folder);
			}
		}
	}

	private function safeName(string $name): string
	{
		$name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename(strtr($name, '\\', '/'))), '-.');

		return $name === '' ? 'bestand' : mb_substr($name, -120);
	}

	private function folderName(string $card): string
	{
		return (string) preg_replace('/[^A-Za-z0-9_-]/', '-', $card);
	}

	private function root(): string
	{
		return rtrim(strtr(storage_path('app/attachments'), '\\', '/'), '/');
	}
}
