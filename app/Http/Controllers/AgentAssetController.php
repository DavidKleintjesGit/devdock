<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunId;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentAssetController extends Controller
{
	/**
	 * A file from the public folder of the copy a round worked in: the
	 * stylesheets a mockup is drawn with, and the fonts and images they point
	 * at. Nothing outside that folder, and only what a page loads.
	 */
	public function show(int $run, string $path): BinaryFileResponse
	{
		$directory = resolve(PromptRunRepositoryContract::class)->getById(new PromptRunId(id: $run))?->directory;
		$public = $directory === null ? false : realpath($directory . '/public');
		$file = $public === false ? false : realpath($public . '/' . $path);

		abort_if($file === false || ! str_starts_with($file, $public . DIRECTORY_SEPARATOR) || ! is_file($file), 404);
		abort_unless(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['css', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true), 404);

		$mime = pathinfo($file, PATHINFO_EXTENSION) === 'css' ? 'text/css' : (mime_content_type($file) ?: 'application/octet-stream');

		// the frame has no origin of its own, so fonts only load when every origin is allowed
		return response()->file($file, [
			'Content-Type' => $mime,
			'Cache-Control' => 'private, max-age=60',
			'Access-Control-Allow-Origin' => '*',
		]);
	}
}
