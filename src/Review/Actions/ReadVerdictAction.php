<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\VerdictObject;

/**
 * Reads what a lens sent back. The verdict stands on the first line of the
 * block; the numbered lines under it are the findings.
 *
 * @method static VerdictObject execute(?string $answer)
 */
class ReadVerdictAction extends Command
{
	private const FENCE = 'verdict';

	public function __construct(
		public readonly ?string $answer,
	) {}

	public function handle(): VerdictObject
	{
		if (blank($this->answer)) {
			return new VerdictObject(VerdictEnum::FAIL, 1, __('Deze lens gaf geen antwoord.'));
		}

		$block = preg_match('/```' . self::FENCE . '\s*\n(.*?)```/s', $this->answer, $found) === 1
			? trim($found[1])
			: trim($this->answer);

		$lines = explode("\n", $block);
		$first = strtoupper(trim((string) array_shift($lines)));

		// a lens that answers outside the block is still answering; only a
		// first line we cannot read makes the round unreliable
		$verdict = match (true) {
			str_starts_with($first, 'PASS') => VerdictEnum::PASS,
			str_starts_with($first, 'FAIL') => VerdictEnum::FAIL,
			default => null,
		};

		if ($verdict === null) {
			return new VerdictObject(VerdictEnum::FAIL, 1, __('Onleesbaar oordeel:') . "\n\n" . $block);
		}

		return new VerdictObject($verdict, $this->findings($lines), $block);
	}

	/**
	 * @param  array<int, string>  $lines
	 */
	private function findings(array $lines): int
	{
		$findings = 0;

		foreach ($lines as $line) {
			if (preg_match('/^\s*\d+\./', $line) === 1) {
				$findings++;
			}
		}

		return $findings;
	}
}
