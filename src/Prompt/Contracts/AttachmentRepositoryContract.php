<?php

declare(strict_types=1);

namespace Src\Prompt\Contracts;

use Src\Prompt\Objects\AttachmentObject;

interface AttachmentRepositoryContract
{
	/**
	 * Adds one piece of an upload. The file arrives in pieces because Herd
	 * takes no request over 2 MB; the last piece makes it whole and returns it.
	 */
	public function storeChunk(string $upload, int $chunk, int $chunks, string $name, string $content): ?AttachmentObject;

	/**
	 * An upload that is whole but not yet sent with a message.
	 */
	public function getPending(string $upload): ?AttachmentObject;

	/**
	 * Moves finished uploads to the folder of a card and returns their full paths.
	 *
	 * @param  list<string>  $uploads
	 * @return list<string>
	 */
	public function attach(string $card, array $uploads): array;

	/**
	 * A file sent with a message, by its full path; null when it lies outside
	 * the attachments folder.
	 */
	public function fromPath(string $path): ?AttachmentObject;

	/**
	 * The full path of a file under the attachments folder; null when it is not there.
	 */
	public function fileAt(string $relativePath): ?string;

	/**
	 * The folder with the files of a card, which its agent may read; null when it has none.
	 */
	public function folderFor(string $card): ?string;

	/**
	 * Throws away every file sent with the messages of a card.
	 */
	public function deleteFor(string $card): void;
}
