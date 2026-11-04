<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\NewFindingObject;
use Src\Review\Objects\VerdictObject;

/**
 * Reads what a lens sent back. The verdict stands on the first line of the
 * block. Under it, a finding from an earlier round stands on a line of its
 * own as "- #12 RESOLVED" or "- #12 OPEN", and a new finding as a numbered
 * line with its place, followed by what is wrong.
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
			return new VerdictObject(VerdictEnum::FAIL, 1, __('Deze lens gaf geen antwoord.'), isReadable: false);
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
			return new VerdictObject(VerdictEnum::FAIL, 1, __('Onleesbaar oordeel:') . "\n\n" . $block, isReadable: false);
		}

		$earlierFindings = $this->earlierFindings($lines);
		$newFindings = $this->newFindings($lines);
		$findings = count(array_filter($earlierFindings, fn (FindingStatusEnum $status): bool => $status === FindingStatusEnum::OPEN)) + count($newFindings);

		return new VerdictObject(
			verdict: $findings > 0 ? VerdictEnum::FAIL : $verdict,
			findings: $findings,
			report: $block,
			earlierFindings: $earlierFindings,
			newFindings: $newFindings,
		);
	}

	/**
	 * What became of the findings of earlier rounds, by their number.
	 *
	 * @param  array<int, string>  $lines
	 * @return array<int, FindingStatusEnum>
	 */
	private function earlierFindings(array $lines): array
	{
		$earlier = [];

		foreach ($lines as $line) {
			if (preg_match('/^\s*-\s*#(\d+)\s+(RESOLVED|OPEN)\b/i', $line, $found) === 1) {
				$earlier[(int) $found[1]] = strtoupper($found[2]) === 'RESOLVED' ? FindingStatusEnum::RESOLVED : FindingStatusEnum::OPEN;
			}
		}

		return $earlier;
	}

	/**
	 * The numbered findings, each with the lines under it as what is wrong.
	 *
	 * @param  array<int, string>  $lines
	 * @return list<NewFindingObject>
	 */
	private function newFindings(array $lines): array
	{
		$newFindings = [];
		$current = null;

		foreach ($lines as $line) {
			if (preg_match('/^\s*\d+\.\s*(.*)$/', $line, $found) === 1) {
				if ($current !== null) {
					$newFindings[] = $this->findingFrom($current);
				}

				$current = ['head' => trim($found[1]), 'body' => []];

				continue;
			}

			if ($current !== null && trim($line) !== '' && preg_match('/^\s*-\s*#\d+/', $line) !== 1) {
				$current['body'][] = trim($line);
			}
		}

		if ($current !== null) {
			$newFindings[] = $this->findingFrom($current);
		}

		return $newFindings;
	}

	/**
	 * A finding from its first line, which names the place, and the lines under it.
	 *
	 * @param  array{head: string, body: list<string>}  $current
	 */
	private function findingFrom(array $current): NewFindingObject
	{
		$looksLikePlace = preg_match('/^[\w.\/\\\\-]+(:\d+(-\d+)?)?$/', $current['head']) === 1;
		$text = trim(implode(' ', $current['body']));

		return new NewFindingObject(
			location: $looksLikePlace ? $current['head'] : null,
			text: $looksLikePlace ? ($text !== '' ? $text : $current['head']) : trim($current['head'] . ' ' . $text),
		);
	}
}
