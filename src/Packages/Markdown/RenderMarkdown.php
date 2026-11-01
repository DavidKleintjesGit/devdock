<?php

declare(strict_types=1);

namespace Src\Packages\Markdown;

class RenderMarkdown
{
	/**
	 * Turns markdown into the markup of the design: headings, lists, quotes and
	 * code blocks. Everything is escaped first, so a file that was read cannot
	 * write html into the page.
	 */
	public function toHtml(string $text): string
	{
		$blocks = [];
		$buffer = [];
		$rows = [];
		$fenced = false;

		foreach (explode("\n", trim($text)) as $line) {
			if (! $fenced && str_starts_with(trim($line), '|')) {
				$rows[] = trim($line);

				continue;
			}

			if ($rows !== []) {
				$blocks[] = $this->table($rows);
				$rows = [];
			}

			if (str_starts_with(trim($line), '```')) {
				if ($fenced) {
					$blocks[] = '<div class="code light mono">' . e(implode("\n", $buffer)) . '</div>';
					$buffer = [];
				}

				$fenced = ! $fenced;

				continue;
			}

			if ($fenced) {
				$buffer[] = $line;

				continue;
			}

			$blocks[] = $this->block($line);
		}

		// an answer that is still being typed can stop halfway a block
		if ($rows !== []) {
			$blocks[] = $this->table($rows);
		}

		if ($fenced && $buffer !== []) {
			$blocks[] = '<div class="code light mono">' . e(implode("\n", $buffer)) . '</div>';
		}

		return '<div class="md">' . implode('', array_filter($blocks)) . '</div>';
	}

	/**
	 * A markdown table: the first row is the head, the dashed row under it is
	 * left out.
	 *
	 * @param  array<int, string>  $rows
	 */
	private function table(array $rows): string
	{
		$cells = array_map(
			fn (string $row) => array_map('trim', explode('|', trim($row, '|'))),
			array_values(array_filter($rows, fn (string $row) => preg_match('/^\|[\s:|-]+\|?$/', $row) !== 1)),
		);

		$head = array_shift($cells) ?? [];

		return '<div class="md-table"><table><thead><tr>'
			. implode('', array_map(fn (string $cell) => '<th>' . $this->inline($cell) . '</th>', $head))
			. '</tr></thead><tbody>'
			. implode('', array_map(
				fn (array $row) => '<tr>' . implode('', array_map(fn (string $cell) => '<td>' . $this->inline($cell) . '</td>', $row)) . '</tr>',
				$cells,
			))
			. '</tbody></table></div>';
	}

	private function block(string $line): ?string
	{
		return match (true) {
			str_starts_with($line, '# ') => '<h3 class="md-h1">' . $this->inline(substr($line, 2)) . '</h3>',
			str_starts_with($line, '## ') => '<h4 class="md-h2">' . $this->inline(substr($line, 3)) . '</h4>',
			(bool) preg_match('/^#{3,} /', $line) => '<h5 class="md-h3">' . $this->inline((string) preg_replace('/^#+ /', '', $line)) . '</h5>',
			(bool) preg_match('/^\s*[-*] /', $line) => '<div class="md-li">' . $this->inline((string) preg_replace('/^\s*[-*] /', '', $line)) . '</div>',
			(bool) preg_match('/^\s*(\d+)[.)] /', $line, $found) => '<div class="md-li numbered" data-number="' . $found[1] . '.">' . $this->inline((string) preg_replace('/^\s*\d+[.)] /', '', $line)) . '</div>',
			(bool) preg_match('/^\s*>/', $line) => '<p class="md-q">' . $this->inline((string) preg_replace('/^\s*>\s?/', '', $line)) . '</p>',
			trim($line) !== '' => '<p class="md-p">' . $this->inline($line) . '</p>',
			default => null,
		};
	}

	/**
	 * Code, bold and links inside a line. Escaping comes first, so the markup
	 * below is the only html that survives.
	 */
	private function inline(string $line): string
	{
		$codes = [];

		// code is taken out first, so a star or bracket inside it stays as written
		$line = (string) preg_replace_callback('/`([^`]+)`/', function (array $found) use (&$codes): string {
			$codes[] = '<code class="mono">' . $found[1] . '</code>';

			return "\u{E000}" . (count($codes) - 1) . "\u{E001}";
		}, e($line));

		$patterns = [
			'/\*\*([^*]+)\*\*/' => '<strong>$1</strong>',
			'/(?<![*\w])\*([^*\n]+)\*(?![*\w])/' => '<em>$1</em>',
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/' => '<a href="$2" target="_blank" rel="noopener">$1</a>',
		];

		$line = (string) preg_replace(array_keys($patterns), array_values($patterns), $line);

		return (string) preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn (array $found) => $codes[(int) $found[1]], $line);
	}
}
