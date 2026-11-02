<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Illuminate\Support\Collection;
use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Objects\ChangeObject;

/**
 * What a round did along the way, read from its event log: every step it
 * took, every message it wrote, and the text it is typing right now.
 */
class TranscriptObject extends ValueObject
{
	/**
	 * @param  array<int, StepObject>  $steps
	 * @param  array<int, string>  $texts
	 */
	public function __construct(
		public readonly array $steps,
		public readonly array $texts,
		public readonly ?string $writing,
	) {
		parent::__construct();
	}

	/**
	 * Everything the agent wrote in this round, the message it is typing
	 * included. Null when it wrote nothing yet.
	 */
	public function said(): ?string
	{
		$said = implode("\n\n", array_filter([...$this->texts, $this->writing]));

		return $said === '' ? null : $said;
	}

	/**
	 * The steps in a few words: how many files were read and changed, and
	 * how many commands ran.
	 */
	public function summary(): string
	{
		return collect([
			$this->readCount() === 0 ? null : trans_choice(':count gelezen|:count gelezen', $this->readCount(), ['count' => $this->readCount()]),
			$this->changeCount() === 0 ? null : trans_choice(':count aangepast|:count aangepast', $this->changeCount(), ['count' => $this->changeCount()]),
			$this->commandCount() === 0 ? null : trans_choice(':count commando|:count commando\'s', $this->commandCount(), ['count' => $this->commandCount()]),
		])->filter()->implode(', ');
	}

	/**
	 * The files this round changed, each once, with the lines it put in and
	 * took out. A file whose first step was a write is new.
	 *
	 * @return array<int, ChangeObject>
	 */
	public function changedFiles(): array
	{
		return collect($this->steps)
			->filter(fn (StepObject $stepObject) => $stepObject->changesFiles())
			->groupBy(fn (StepObject $stepObject) => $stepObject->subject)
			->map(fn (Collection $steps, string $path) => ChangeObject::from([
				'path' => $path,
				'status' => $steps->first()->tool === 'Write' ? 'A' : 'M',
				'added' => $steps->sum('added'),
				'removed' => $steps->sum('removed'),
			]))
			->values()
			->all();
	}

	public function readCount(): int
	{
		return count(array_filter($this->steps, fn (StepObject $stepObject) => $stepObject->readsFiles()));
	}

	public function changeCount(): int
	{
		return count(array_filter($this->steps, fn (StepObject $stepObject) => $stepObject->changesFiles()));
	}

	public function commandCount(): int
	{
		return count(array_filter($this->steps, fn (StepObject $stepObject) => $stepObject->runsCommand()));
	}

	public function latestStep(): ?StepObject
	{
		return $this->steps === [] ? null : $this->steps[array_key_last($this->steps)];
	}
}
