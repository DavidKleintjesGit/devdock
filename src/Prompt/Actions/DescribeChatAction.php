<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;

/**
 * How an agent writes so the chat in DevDock can show it: a screen design as
 * markup that is drawn, and a question with fixed answers as buttons. It is
 * the other half of GetConversationAction: change a block here and change
 * the reading there.
 *
 * The block names are a machine format, so they stay as they are written
 * here in every language.
 *
 * @method static string execute()
 */
class DescribeChatAction extends Command
{
	public function handle(): string
	{
		return implode("\n", [
			__('Je antwoorden verschijnen in een chat in DevDock. Daar wordt markdown getoond, en twee blokken krijgen een eigen weergave.'),
			'',
			__('- Een schermontwerp of mockup schrijf je als HTML in een mockup-blok, nooit als tekening met tekens. Dat geldt voor elk scherm en elke variant, ook als je eerder in dit gesprek met tekens tekende: zet die dan om naar HTML.'),
			__('- Lees de layout van dat scherm voor je tekent, en zet achter de naam van het blok alle stylesheets die die layout laadt, in dezelfde volgorde en als paden onder de map public. Dan ziet het ontwerp eruit als het echte scherm. Gebruik de classes en componenten die het project al heeft. Scripts draaien niet.'),
			'',
			'```mockup vendor/theme/theme.css css/app.css',
			'<div class="panel panel-default">…</div>',
			'```',
			'',
			__('- Stel je een vraag waarop een paar duidelijke antwoorden mogelijk zijn, sluit dan af met een choices-blok: één regel per antwoord, geschreven zoals ik het zou zeggen. Elk antwoord wordt een knop die het als mijn bericht terugstuurt.'),
			'',
			'```choices',
			'- <answer>',
			'- <answer>',
			'```',
			'',
			__('- Een attachments-blok onder mijn bericht noemt de bestanden die ik meestuur, één volledig pad per regel. Lees ze met de Read-tool voor je antwoordt; een afbeelding of pdf zie je dan zoals ik hem zie.'),
		]);
	}
}
