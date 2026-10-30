<?php

declare(strict_types=1);

return [

	'version' => '0.1',

	/*
	|--------------------------------------------------------------------------
	| Atlassian
	|--------------------------------------------------------------------------
	|
	| Jira en Bitbucket delen sinds de app passwords verdwenen (28 juli 2026)
	| één API-token, met het e-mailadres als gebruikersnaam. Het token heeft
	| genoeg aan lezen: read:jira-work, read:repository en read:pullrequest.
	|
	*/

	// het e-mailadres is de gebruikersnaam bij beide tokens
	'atlassian' => [
		'email' => env('ATLASSIAN_EMAIL'),
	],

	'jira' => [
		'base_url' => env('JIRA_BASE_URL'),
		'token' => env('JIRA_TOKEN'),
	],

	'bitbucket' => [
		'workspace' => env('BITBUCKET_WORKSPACE'),

		// je eigen naam zoals Bitbucket die toont; het bord houdt alleen de pull
		// requests over waar jij auteur of reviewer bent
		'me' => env('BITBUCKET_ME'),

		// een eigen token, met Bitbucket-scopes
		'token' => env('BITBUCKET_TOKEN'),
	],

	/*
	|--------------------------------------------------------------------------
	| Paden
	|--------------------------------------------------------------------------
	|
	| De twee mappen op schijf die DevDock leest. Een andere machine hoeft
	| alleen zijn .env aan te passen.
	|
	*/

	'paths' => [
		'sites' => env('DEVDOCK_SITES_PATH'),
		'sessions' => env('DEVDOCK_SESSIONS_PATH'),
		'core' => env('DEVDOCK_CORE_PATH'),
	],

	/*
	|--------------------------------------------------------------------------
	| Werkkopieën
	|--------------------------------------------------------------------------
	|
	| Een worktree staat in de projectmap zelf, maar krijgt van git alleen wat
	| in de commit zit. Alles wat genegeerd is moet er dus apart in: de zware
	| mappen worden geleend van het project, de kleine bestanden gekopieerd.
	|
	| Lenen mag alleen als het slotbestand gelijk is; wijkt dat af, dan heeft
	| die branch andere pakketten nodig en moet er echt geïnstalleerd worden.
	|
	*/

	'worktree' => [
		'folder' => '.worktrees',

		// hoe lang de staat van een werkkopie blijft staan. git status over een
		// grote repository kost een vijfde seconde per werkkopie, en het bord
		// vraagt het bij elke klik opnieuw
		// wat DevDock zelf verandert haalt de regel meteen weg, dus hij mag lang
		'status_seconds' => (int) env('DEVDOCK_WORKTREE_STATUS_SECONDS', 60),

		'share' => [
			'vendor' => 'composer.lock',
			'node_modules' => 'package-lock.json',
		],

		'copy' => [
			'.env',
			'.env.testing',
			'database/database.sqlite',
			'CLAUDE.local.md',
			'.claude/settings.json',

			// settings.json zet de mcp-servers aan die hier gedefinieerd staan;
			// zonder deze twee staat in de werkkopie een schakelaar zonder draad
			'.mcp.json',
			'.claude/atlassian-mcp.sh',
			'.claude/rules/local',
			'.claude/skills',
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Herd
	|--------------------------------------------------------------------------
	|
	| Elke map in Herds eigen Sites-map antwoordt op <naam>.<tld>. Daar zet een
	| werkkopie een verwijzing neer, zodat je de branch in de browser opent
	| zonder iets te parkeren. Leeg laten: dan leest DevDock Herds config.
	|
	*/

	'herd' => [
		'sites' => env('DEVDOCK_HERD_SITES_PATH'),
		'tld' => env('DEVDOCK_HERD_TLD'),
	],

	/*
	|--------------------------------------------------------------------------
	| Het bord
	|--------------------------------------------------------------------------
	|
	| Hoe ver de Klaar-kolom terugkijkt. Zonder venster groeit die eindeloos
	| door met alles wat ooit gemerged is.
	|
	*/

	'board' => [
		'done_days' => (int) env('DEVDOCK_DONE_DAYS', 14),
		'show_branch' => filter_var(env('DEVDOCK_SHOW_BRANCH', true), FILTER_VALIDATE_BOOL),
		'columns' => array_values(array_filter(explode(',', (string) env('DEVDOCK_COLUMNS', 'todo,busy,review,merge,done')))),
	],

	/*
	|--------------------------------------------------------------------------
	| Weergave en ophalen
	|--------------------------------------------------------------------------
	|
	| Wat de instellingenpagina schrijft. Die zet de waarden in .env, zodat de
	| pagina's en `devdock:fetch` hetzelfde lezen.
	|
	*/

	'theme' => env('DEVDOCK_THEME', 'system'),

	// het ophalen draait als los proces; over het web is php zelf fpm of cgi
	'php_binary' => env('DEVDOCK_PHP_BINARY'),

	// de Claude Code-uitvoerbare, voor het promten vanaf een kaart
	// na dit aantal rondes zonder schone uitkomst stopt het en kom jij erbij
	'review' => [
		'max_rounds' => (int) env('DEVDOCK_MAX_ROUNDS', 3),

		// een lens mag lang nadenken; stilte betekent dat hij weg is. Daarom
		// telt niet de klok maar de tijd sinds het laatste teken van leven
		'lens_timeout' => (int) env('DEVDOCK_LENS_TIMEOUT', 1800),

		// tijdens één aanroep stuurt Claude niets: draait een lens de testsuite,
		// dan is hij minutenlang stil zonder dat er iets mis is
		'lens_idle' => (int) env('DEVDOCK_LENS_IDLE', 600),

		// zoveel lenzen tegelijk; elke worker stopt zodra de rij leeg is
		'workers' => (int) env('DEVDOCK_LENS_WORKERS', 5),
	],

	'claude_binary' => env('DEVDOCK_CLAUDE_BINARY'),
	'claude_token' => env('DEVDOCK_CLAUDE_TOKEN'),

	// hoe lang één prompt mag duren voordat het bord hem afbreekt
	'prompt_timeout' => (int) env('DEVDOCK_PROMPT_TIMEOUT', 900),

	'refresh_seconds' => (int) env('DEVDOCK_REFRESH_SECONDS', 0),

	/*
	|--------------------------------------------------------------------------
	| Drempels
	|--------------------------------------------------------------------------
	|
	| De grenzen waarop het bord sessies indeelt.
	|
	*/

	'sessions' => [
		'abandoned_up_to' => (int) env('DEVDOCK_ABANDONED_UP_TO', 2),
		'large_from_mb' => (int) env('DEVDOCK_LARGE_FROM_MB', 15),
		'old_after_days' => (int) env('DEVDOCK_OLD_AFTER_DAYS', 14),
	],

];
