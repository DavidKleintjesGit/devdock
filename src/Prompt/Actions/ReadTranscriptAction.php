<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Objects\StepObject;
use Src\Prompt\Objects\TranscriptObject;

/**
 * Reads the event log of a round back into the steps it took and the text it
 * is writing. Null when the round left no log, as rounds from before the log
 * existed did not.
 *
 * @method static ?TranscriptObject execute(string $logPath, ?string $directory)
 */
class ReadTranscriptAction extends Command
{
	/**
	 * How much of a command shows in the list of steps.
	 */
	private const SUBJECT_LENGTH = 90;

	public function __construct(
		public readonly string $logPath,
		public readonly ?string $directory,
	) {}

	public function handle(): ?TranscriptObject
	{
		if (! is_file($this->logPath)) {
			return null;
		}

		$steps = [];
		$texts = [];
		$current = '';

		foreach (file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
			$event = json_decode($line, true);

			if (! is_array($event)) {
				continue;
			}

			$streamed = $event['event'] ?? [];

			// Claude Code's stream format: a message opens, its text arrives in
			// deltas, and the whole message follows as one assistant event
			if (($event['type'] ?? null) === 'stream_event' && ($streamed['type'] ?? null) === 'message_start') {
				$current = '';
			}

			if (($event['type'] ?? null) === 'stream_event' && ($streamed['delta']['type'] ?? null) === 'text_delta') {
				$current .= (string) ($streamed['delta']['text'] ?? '');
			}

			if (($event['type'] ?? null) !== 'assistant') {
				continue;
			}

			// only the agent's own messages: a sub-agent it started reports through a tool
			if (($event['parent_tool_use_id'] ?? null) !== null) {
				continue;
			}

			foreach ($event['message']['content'] ?? [] as $block) {
				if (($block['type'] ?? null) === 'text' && trim((string) ($block['text'] ?? '')) !== '') {
					$texts[] = (string) $block['text'];
					$current = '';
				}

				if (($block['type'] ?? null) === 'tool_use' && filled($block['name'] ?? null)) {
					$steps[] = new StepObject(
						tool: (string) $block['name'],
						subject: $this->subjectOf((array) ($block['input'] ?? [])),
					);
				}
			}
		}

		return new TranscriptObject(
			steps: $steps,
			texts: $texts,
			writing: $current !== '' ? $current : null,
		);
	}

	/**
	 * What a tool was pointed at, in a few words: the file, the search or the
	 * command.
	 */
	private function subjectOf(array $input): string
	{
		$file = $input['file_path'] ?? $input['notebook_path'] ?? $input['path'] ?? null;

		if (is_string($file)) {
			return $this->relative($file);
		}

		$subject = $input['description'] ?? $input['command'] ?? $input['pattern'] ?? $input['query'] ?? $input['url'] ?? '';

		$firstLine = strtok(is_string($subject) ? $subject : '', "\n");

		return mb_strimwidth($firstLine === false ? '' : $firstLine, 0, self::SUBJECT_LENGTH, '…');
	}

	private function relative(string $file): string
	{
		$file = str_replace('\\', '/', $file);
		$directory = rtrim(str_replace('\\', '/', (string) $this->directory), '/') . '/';

		return $this->directory !== null && stripos($file, $directory) === 0
			? substr($file, strlen($directory))
			: $file;
	}
}
