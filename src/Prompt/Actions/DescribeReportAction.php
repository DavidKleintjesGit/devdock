<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;

/**
 * The closing every prompt asks for. It is the other half of ReadReportAction:
 * change the shape here and change the reading there, or the card falls back
 * to showing the whole answer.
 *
 * The blocks themselves are a machine format, so they stay as they are
 * written here in every language the rest of the prompt is in.
 *
 * @method static string execute()
 */
class DescribeReportAction extends Command
{
	public function handle(): string
	{
		return implode("\n", [
			__('- Sluit af met deze twee blokken, precies in deze vorm, zodat het bord je antwoord kan lezen.'),
			'',
			'```report',
			'status: done | question | blocked',
			'summary: <one sentence on what this round produced>',
			'done:',
			'- <one line per thing you changed>',
			'open:',
			'- <what is still left, leave the field out when nothing is>',
			'question: <only with status question: what you need from me>',
			'```',
			'',
			'```commit',
			'<the commit title: one sentence, imperative, 72 characters at most>',
			'',
			'- <what changed and why>',
			'```',
		]);
	}
}
