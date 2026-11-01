<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\ExchangeObject;
use Src\Prompt\Objects\MockupObject;
use Src\Prompt\Objects\PromptRunEntity;

/**
 * The conversation with a task's agent, oldest exchange first. It is built
 * from what we sent ourselves rather than from Claude's session file: that
 * way it reads the same whether or not the file is still reachable.
 *
 * @method static array execute(string $card)
 */
class GetConversationAction extends Command
{
	/**
	 * The blocks an answer closes with. They are read out separately, so they
	 * are taken out of what the agent said in its own words.
	 */
	private const FENCES = ['report', 'commit', 'choices'];

	public function __construct(
		public readonly string $card,
	) {}

	/**
	 * @return array<int, ExchangeObject>
	 */
	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		RenderMarkdown $renderMarkdown,
	): array {
		$exchangeObjects = [];

		foreach ($promptRunRepository->getAllForCard($this->card) as $promptRunEntity) {
			$transcriptObject = ReadTranscriptAction::execute(
				$promptRunRepository->logPath($promptRunEntity->promptRunId),
				$promptRunEntity->directory,
			);

			// the answer of a round is only its last message; the log holds every
			// message it wrote, and the one it is typing while it still works
			$said = $transcriptObject?->said() ?? ($promptRunEntity->isRunning() ? null : $promptRunEntity->output);

			$exchangeObjects[] = new ExchangeObject(
				run: $promptRunEntity,
				passages: $this->passages($renderMarkdown, $said),
				report: $promptRunEntity->isRunning() ? null : ReadReportAction::execute($promptRunEntity->output),
				choices: $promptRunEntity->isRunning() ? [] : $this->choices($promptRunEntity),
				transcript: $transcriptObject,
			);
		}

		return $exchangeObjects;
	}

	/**
	 * What the agent wrote in its own words, in order: text, and the screens
	 * it drew in between. The closing blocks are lifted out first.
	 *
	 * @return array<int, string|MockupObject>
	 */
	private function passages(RenderMarkdown $renderMarkdown, ?string $output): array
	{
		if ($output === null) {
			return [];
		}

		foreach (self::FENCES as $fence) {
			$output = (string) preg_replace('/```' . $fence . '\s*\n.*?```/s', '', $output);
		}

		$parts = preg_split('/```mockup([^\n]*)\n(.*?)```/s', $output, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$output];
		$passages = [];

		// the split hands back text, stylesheets, markup, text, and so on
		foreach (array_chunk($parts, 3) as $chunk) {
			if (trim($chunk[0]) !== '') {
				$passages[] = $renderMarkdown->toHtml($chunk[0]);
			}

			if (isset($chunk[2]) && trim($chunk[2]) !== '') {
				$passages[] = $this->mockup($chunk[1], $chunk[2]);
			}
		}

		return $passages;
	}

	/**
	 * A drawn screen with its stylesheets: the ones named after the block, and
	 * the ones the agent linked inside the markup itself, which are lifted out
	 * so they load from the project and not from DevDock.
	 */
	private function mockup(string $fenceLine, string $html): MockupObject
	{
		preg_match_all('/<link\b[^>]*\brel=["\']?stylesheet[^>]*>/i', $html, $links);

		$linked = collect($links[0])
			->map(fn (string $link) => preg_match('/\bhref=["\']([^"\']+)["\']/i', $link, $found) === 1 ? $found[1] : null)
			->filter();

		return new MockupObject(
			html: trim((string) preg_replace('/<link\b[^>]*\brel=["\']?stylesheet[^>]*>\s*/i', '', $html)),
			stylesheets: collect(preg_split('/\s+/', trim($fenceLine)) ?: [])
				->merge($linked)
				->filter()
				->unique()
				->values()
				->all(),
		);
	}

	/**
	 * The answers the agent offered, one per line of its choices block.
	 *
	 * @return array<int, string>
	 */
	private function choices(PromptRunEntity $promptRunEntity): array
	{
		if (preg_match('/```choices\s*\n(.*?)```/s', (string) $promptRunEntity->output, $found) !== 1) {
			return [];
		}

		return collect(explode("\n", $found[1]))
			->map(fn (string $line) => trim(ltrim(trim($line), '-*')))
			->filter()
			->values()
			->all();
	}
}
