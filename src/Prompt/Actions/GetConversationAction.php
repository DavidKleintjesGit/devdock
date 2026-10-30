<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\ExchangeObject;

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
	private const FENCES = ['report', 'commit'];

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
			$said = $this->withoutFences($promptRunEntity->output);

			$exchangeObjects[] = new ExchangeObject(
				run: $promptRunEntity,
				body: $said === null ? null : $renderMarkdown->toHtml($said),
				report: ReadReportAction::execute($promptRunEntity->output),
			);
		}

		return $exchangeObjects;
	}

	/**
	 * What the agent wrote in its own words: the whole answer with the closing
	 * blocks lifted out, so the plan it laid out is not lost behind a summary.
	 */
	private function withoutFences(?string $output): ?string
	{
		if ($output === null) {
			return null;
		}

		foreach (self::FENCES as $fence) {
			$output = (string) preg_replace('/```' . $fence . '\s*\n.*?```/s', '', $output);
		}

		return trim($output) ?: null;
	}
}
