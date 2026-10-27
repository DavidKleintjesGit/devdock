<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Bronnen
	|--------------------------------------------------------------------------
	|
	| DevDock leest vier bronnen: Jira en Bitbucket over het netwerk, en twee
	| mappen op schijf. De paden staan hier zodat een andere machine alleen
	| zijn .env hoeft aan te passen.
	|
	*/

	'jira' => [
		'base_url' => env('JIRA_BASE_URL'),
		'email' => env('JIRA_EMAIL'),
		'token' => env('JIRA_TOKEN'),
	],

	'bitbucket' => [
		'workspace' => env('BITBUCKET_WORKSPACE'),
		'user' => env('BITBUCKET_USER'),
		'app_password' => env('BITBUCKET_APP_PASSWORD'),
	],

	'paths' => [
		'sites' => env('DEVDOCK_SITES_PATH'),
		'sessions' => env('DEVDOCK_SESSIONS_PATH'),
		'core' => env('DEVDOCK_CORE_PATH'),
	],

	/*
	|--------------------------------------------------------------------------
	| Drempels
	|--------------------------------------------------------------------------
	|
	| De grenzen waarop het bord sessies indeelt. Overschrijfbaar per gebruiker
	| zodra de instellingenpagina er is.
	|
	*/

	'sessions' => [
		'abandoned_up_to' => 2,
		'large_from_mb' => 15,
		'old_after_days' => 14,
	],

];
