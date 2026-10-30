<?php

declare(strict_types=1);

namespace Src\Ticket\Repositories;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Src\Ticket\Contracts\TicketSourceRepositoryContract;
use Src\Ticket\Objects\TicketObject;
use Src\Ticket\Objects\TicketObjectCollection;

class JiraTicketRepository implements TicketSourceRepositoryContract
{
	/**
	 * The address where this token is accepted; settled once per run of calls.
	 */
	private ?string $baseUrl = null;

	public function isConfigured(): bool
	{
		return filled(config('devdock.jira.base_url'))
			&& filled(config('devdock.atlassian.email'))
			&& filled(config('devdock.jira.token'));
	}

	public function getAssigned(): TicketObjectCollection
	{
		$site = rtrim((string) config('devdock.jira.base_url'), '/');
		$ticketObjects = [];
		$nextPageToken = null;

		do {
			$result = $this->client()->get('/rest/api/3/search/jql', array_filter([
				'jql' => 'assignee = currentUser() AND statusCategory != Done ORDER BY updated DESC',
				'fields' => 'summary,description,status,issuetype,priority,updated',
				'maxResults' => 100,
				'nextPageToken' => $nextPageToken,
			]));

			// the message sits in the answer; after ->throw() it can no longer be read
			if ($result->failed()) {
				throw new RuntimeException($this->reason($result));
			}

			$response = $result->json();

			foreach ($response['issues'] ?? [] as $issue) {
				$ticketObjects[] = TicketObject::from($issue + [
					'url' => $site . '/browse/' . $issue['key'],
					'description' => $this->plainText($issue['fields']['description'] ?? null),
				]);
			}

			$nextPageToken = $response['nextPageToken'] ?? null;
		} while ($nextPageToken !== null);

		return TicketObjectCollection::fromArray($ticketObjects);
	}

	/**
	 * Jira hands a description back as a tree of blocks. Only the words are
	 * of use in a prompt, so the tree is walked and flattened, with a blank
	 * line between paragraphs and a dash in front of list items.
	 */
	private function plainText(?array $node): ?string
	{
		if ($node === null) {
			return null;
		}

		$text = trim($this->walk($node));

		return $text === '' ? null : $text;
	}

	private function walk(array $node): string
	{
		$type = $node['type'] ?? '';

		if ($type === 'text') {
			return (string) ($node['text'] ?? '');
		}

		$inside = '';

		foreach ($node['content'] ?? [] as $child) {
			$inside .= $this->walk($child);
		}

		return match ($type) {
			'paragraph', 'heading', 'codeBlock' => $inside .'

',
			'listItem' => '- ' . trim($inside) .'
',
			'hardBreak' => '
',
			default => $inside,
		};
	}

	/**
	 * A classic token works on the address of the site itself; a token with
	 * scopes only through the Atlassian gateway, with the cloud id in it. On the
	 * wrong address Jira gives no error but an empty list, so the address is
	 * settled before anything is fetched.
	 */
	private function baseUrl(): string
	{
		if ($this->baseUrl !== null) {
			return $this->baseUrl;
		}

		$site = rtrim((string) config('devdock.jira.base_url'), '/');

		if ($this->accepts($site)) {
			return $this->baseUrl = $site;
		}

		$cloudId = $this->cloudId($site);
		$gateway = 'https://api.atlassian.com/ex/jira/' . $cloudId;

		if ($cloudId !== null && $this->accepts($gateway)) {
			return $this->baseUrl = $gateway;
		}

		throw new RuntimeException(__('Jira weigert het token. Controleer het token en zijn scopes.'));
	}

	private function accepts(string $baseUrl): bool
	{
		return $this->clientFor($baseUrl)->get('/rest/api/3/myself')->successful();
	}

	/**
	 * The cloud id of the site, which can be asked for without signing in.
	 */
	private function cloudId(string $site): ?string
	{
		$result = Http::acceptJson()->timeout(10)->get($site . '/_edge/tenant_info');

		return $result->successful() ? $result->json('cloudId') : null;
	}

	/**
	 * What went wrong, in words. On a 401 the answer is sometimes empty; the
	 * token or a missing scope is then the only sensible explanation.
	 */
	private function reason(Response $result): string
	{
		$message = $result->json('error.message') ?? $result->json('errorMessages.0');

		if (filled($message)) {
			return $message;
		}

		return $result->status() === 401
			? __(':service weigert het token. Controleer het token en zijn scopes.', ['service' => 'Jira'])
			: __(':service gaf status :status.', ['service' => 'Jira', 'status' => $result->status()]);
	}

	private function client(): PendingRequest
	{
		return $this->clientFor($this->baseUrl());
	}

	private function clientFor(string $baseUrl): PendingRequest
	{
		return Http::baseUrl($baseUrl)
			->withBasicAuth(config('devdock.atlassian.email'), config('devdock.jira.token'))
			->acceptJson()
			->timeout(20)
			->retry(2, 200, throw: false);
	}
}
