<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Src\Prompt\Contracts\AttachmentRepositoryContract;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
	/**
	 * Takes one piece of a file dropped on the chat; the answer to the last
	 * piece names the upload the message can carry.
	 */
	public function store(): JsonResponse
	{
		try {
			$attachmentObject = resolve(AttachmentRepositoryContract::class)->storeChunk(
				(string) request()->header('X-Upload'),
				(int) request()->header('X-Chunk'),
				(int) request()->header('X-Chunks'),
				rawurldecode((string) request()->header('X-File-Name')),
				(string) request()->getContent(),
			);
		} catch (RuntimeException $failure) {
			return response()->json(['message' => $failure->getMessage()], 422);
		}

		return response()->json($attachmentObject === null ? ['done' => false] : [
			'done' => true,
			'name' => $attachmentObject->name,
			'size' => $attachmentObject->sizeLabel(),
		]);
	}

	/**
	 * A file sent with a message, to show in the chat or open on its own.
	 */
	public function show(string $path): BinaryFileResponse
	{
		$file = resolve(AttachmentRepositoryContract::class)->fileAt($path);

		abort_if($file === null, 404);

		return response()->file($file, ['Cache-Control' => 'private, max-age=3600']);
	}
}
