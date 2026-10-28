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
		'columns' => array_values(array_filter(explode(',', (string) env('DEVDOCK_COLUMNS', 'todo,busy,changes,merge,done')))),
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
	'claude_binary' => env('DEVDOCK_CLAUDE_BINARY'),

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
