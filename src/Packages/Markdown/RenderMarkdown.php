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
		$fenced = false;

		foreach (explode("\n", trim($text)) as $line) {
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

		return '<div class="md">' . implode('', array_filter($blocks)) . '</div>';
	}

	private function block(string $line): ?string
	{
		return match (true) {
			str_starts_with($line, '# ') => '<h3 class="md-h1">' . $this->inline(substr($line, 2)) . '</h3>',
			str_starts_with($line, '## ') => '<h4 class="md-h2">' . $this->inline(substr($line, 3)) . '</h4>',
			(bool) preg_match('/^#{3,} /', $line) => '<h5 class="md-h3">' . $this->inline((string) preg_replace('/^#+ /', '', $line)) . '</h5>',
			(bool) preg_match('/^\s*[-*] /', $line) => '<div class="md-li">' . $this->inline((string) preg_replace('/^\s*[-*] /', '', $line)) . '</div>',
			(bool) preg_match('/^\s*>/', $line) => '<p class="md-q">' . $this->inline((string) preg_replace('/^\s*>\s?/', '', $line)) . '</p>',
			trim($line) !== '' => '<p class="md-p">' . $this->inline($line) . '</p>',
			default => null,
		};
	}

	private function inline(string $line): string
	{
		return (string) preg_replace('/`([^`]+)`/', '<code class="mono">$1</code>', e($line));
	}
}
