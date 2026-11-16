<?php

declare(strict_types=1);

namespace Src\Library\Repositories;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Src\Library\Contracts\LibraryRemoteRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;

/**
 * Reads a library straight from its repository on Bitbucket or GitHub, at
 * the branch in its address, without a checkout on disk. Bitbucket is read
 * with DevDock's Atlassian token, GitHub with its own token when one is set.
 * A skill comes back packed the way a folder on disk packs it: every file of
 * the skill by its path, as JSON.
 */
class LibraryRemoteRepository implements LibraryRemoteRepositoryContract
{
	private const BITBUCKET = '#^https?://bitbucket\.org/([^/]+)/([^/]+)/src/([^/]+)/?(.*?)/?$#';

	private const GITHUB = '#^https?://github\.com/([^/]+)/([^/]+)/(?:tree|blob)/([^/]+)/?(.*?)/?$#';

	public function isAddress(string $location): bool
	{
		return preg_match(self::BITBUCKET, trim($location)) === 1 || preg_match(self::GITHUB, trim($location)) === 1;
	}

	public function readFolder(string $address, LibraryItemKindEnum $kind): array
	{
		$found = [];
		$entries = $this->list($address, '');

		if ($kind === LibraryItemKindEnum::RULE) {
			foreach ($entries as $entry) {
				if ($entry['type'] === 'file' && str_ends_with(strtolower($entry['name']), '.md')) {
					$found[$entry['name']] = $this->read($address, $entry['name']);
				}
			}

			return $found;
		}

		foreach ($entries as $entry) {
			if ($entry['type'] !== 'dir') {
				continue;
			}

			$files = $this->filesUnder($address, $entry['name']);

			if (isset($files['SKILL.md'])) {
				ksort($files);
				$found[$entry['name']] = (string) json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			}
		}

		return $found;
	}

	/**
	 * Every file under a folder of the repository, by its path inside that folder.
	 *
	 * @return array<string, string>
	 */
	private function filesUnder(string $address, string $folder): array
	{
		$files = [];

		foreach ($this->list($address, $folder) as $entry) {
			$relative = $folder . '/' . $entry['name'];

			if ($entry['type'] !== 'dir') {
				$files[$entry['name']] = $this->read($address, $relative);

				continue;
			}

			foreach ($this->filesUnder($address, $relative) as $path => $contents) {
				$files[$entry['name'] . '/' . $path] = $contents;
			}
		}

		return $files;
	}

	/**
	 * The files and folders directly inside a folder of the repository.
	 *
	 * @return list<array{name: string, type: string}>
	 */
	private function list(string $address, string $relative): array
	{
		[$host, $owner, $repository, $branch, $root] = $this->partsOf($address);
		$path = trim($root . '/' . $relative, '/');

		if ($host === 'github') {
			$response = $this->github()->get('/repos/' . $owner . '/' . $repository . '/contents/' . $path, ['ref' => $branch]);
			$this->ensure($response, 'GitHub');

			return collect($response->json())
				->map(fn (array $item): array => ['name' => (string) $item['name'], 'type' => $item['type'] === 'dir' ? 'dir' : 'file'])
				->all();
		}

		$entries = [];
		$url = '/2.0/repositories/' . $owner . '/' . $repository . '/src/' . $branch . '/' . ($path === '' ? '' : $path . '/');
		$query = ['pagelen' => 100];

		do {
			$response = $this->bitbucket()->get($url, $query);
			$this->ensure($response, 'Bitbucket');

			foreach ($response->json('values') ?? [] as $item) {
				$entries[] = [
					'name' => basename((string) $item['path']),
					'type' => $item['type'] === 'commit_directory' ? 'dir' : 'file',
				];
			}

			$url = $response->json('next');
			$query = [];
		} while (filled($url));

		return $entries;
	}

	private function read(string $address, string $relative): string
	{
		[$host, $owner, $repository, $branch, $root] = $this->partsOf($address);
		$path = trim($root . '/' . $relative, '/');

		$response = $host === 'github'
			? $this->github()->withHeaders(['Accept' => 'application/vnd.github.raw'])->get('/repos/' . $owner . '/' . $repository . '/contents/' . $path, ['ref' => $branch])
			: $this->bitbucket()->get('/2.0/repositories/' . $owner . '/' . $repository . '/src/' . $branch . '/' . $path);

		$this->ensure($response, $host === 'github' ? 'GitHub' : 'Bitbucket');

		return $response->body();
	}

	/**
	 * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
	 */
	private function partsOf(string $address): array
	{
		if (preg_match(self::BITBUCKET, trim($address), $found) === 1) {
			return ['bitbucket', $found[1], $found[2], $found[3], $found[4]];
		}

		if (preg_match(self::GITHUB, trim($address), $found) === 1) {
			return ['github', $found[1], $found[2], $found[3], $found[4]];
		}

		throw new RuntimeException('Not a repository address: ' . $address);
	}

	private function ensure(Response $response, string $service): void
	{
		if ($response->failed()) {
			throw new RuntimeException($service . ' ' . $response->status() . ': ' . ($response->json('error.message') ?? $response->json('message') ?? 'no answer'));
		}
	}

	private function bitbucket(): PendingRequest
	{
		return Http::baseUrl('https://api.bitbucket.org')
			->withBasicAuth((string) config('devdock.atlassian.email'), (string) config('devdock.bitbucket.token'))
			->timeout(20)
			->retry(2, 200, throw: false);
	}

	private function github(): PendingRequest
	{
		$pendingRequest = Http::baseUrl('https://api.github.com')
			->withHeaders(['Accept' => 'application/vnd.github+json'])
			->timeout(20)
			->retry(2, 200, throw: false);

		return filled(config('devdock.github.token'))
			? $pendingRequest->withToken((string) config('devdock.github.token'))
			: $pendingRequest;
	}
}
