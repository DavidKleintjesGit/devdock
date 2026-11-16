<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;

/**
 * The ground rules every turn of the agent carries, with the closing the board
 * reads: look before you change, nothing leaves the machine unasked, and end
 * every answer with a report.
 *
 * @method static string execute()
 */
class DescribeWorkAction extends Command
{
	public function handle(): string
	{
		return implode("\n", [
			__('Werkwijze:'),
			__('- Leg bij een nieuwe opdracht eerst een plan voor voordat je iets aanpast, nadat je de code hebt gelezen die erbij hoort. Heb ik ja gezegd, voer het dan uit.'),
			__('- Volg de regels uit CLAUDE.md en draai de tests van wat je aanraakt.'),
			__('- Git lees je alleen. Committen, pushen en rebasen doe ik met de knoppen in DevDock, en DevDock houdt het voor jou tegen. Laat je werk in de werkkopie staan.'),
			__('- Plaats nergens een reactie zonder dat ik daar eerst ja op heb gezegd.'),
			DescribeReportAction::execute(),
		]);
	}
}
