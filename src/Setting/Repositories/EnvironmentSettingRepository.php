<?php

declare(strict_types=1);

namespace Src\Setting\Repositories;

use Illuminate\Support\Facades\Artisan;
use Src\Setting\Contracts\SettingRepositoryContract;
use Src\Setting\Objects\SettingEntity;
use Src\Setting\Objects\SettingObject;

class EnvironmentSettingRepository implements SettingRepositoryContract
{
	public function get(): SettingEntity
	{
		return new SettingEntity(
			atlassianEmail: (string) config('devdock.atlassian.email'),
			jiraBaseUrl: (string) config('devdock.jira.base_url'),
			hasJiraToken: filled(config('devdock.jira.token')),
			bitbucketWorkspace: (string) config('devdock.bitbucket.workspace'),
			bitbucketMe: (string) config('devdock.bitbucket.me'),
			hasBitbucketToken: filled(config('devdock.bitbucket.token')),
			sitesPath: (string) config('devdock.paths.sites'),
			sessionsPath: (string) config('devdock.paths.sessions'),
			corePath: (string) config('devdock.paths.core'),
			refreshSeconds: (int) config('devdock.refresh_seconds'),
			columns: (array) config('devdock.board.columns'),
			doneDays: (int) config('devdock.board.done_days'),
			abandonedUpToPrompts: (int) config('devdock.sessions.abandoned_up_to'),
			largeFromMegabytes: (int) config('devdock.sessions.large_from_mb'),
			oldAfterDays: (int) config('devdock.sessions.old_after_days'),
			theme: (string) config('devdock.theme'),
			showBranch: (bool) config('devdock.board.show_branch'),
			language: app()->getLocale(),
		);
	}

	public function update(SettingObject $settingObject): SettingEntity
	{
		$values = [
			'ATLASSIAN_EMAIL' => $settingObject->atlassianEmail,
			'JIRA_BASE_URL' => $settingObject->jiraBaseUrl,
			'BITBUCKET_WORKSPACE' => $settingObject->bitbucketWorkspace,
			'BITBUCKET_ME' => $settingObject->bitbucketMe,
			'DEVDOCK_SITES_PATH' => $settingObject->sitesPath,
			'DEVDOCK_SESSIONS_PATH' => $settingObject->sessionsPath,
			'DEVDOCK_CORE_PATH' => $settingObject->corePath,
			'DEVDOCK_REFRESH_SECONDS' => (string) $settingObject->refreshSeconds,
			'DEVDOCK_COLUMNS' => implode(',', $settingObject->columns),
			'DEVDOCK_DONE_DAYS' => (string) $settingObject->doneDays,
			'DEVDOCK_ABANDONED_UP_TO' => (string) $settingObject->abandonedUpToPrompts,
			'DEVDOCK_LARGE_FROM_MB' => (string) $settingObject->largeFromMegabytes,
			'DEVDOCK_OLD_AFTER_DAYS' => (string) $settingObject->oldAfterDays,
			'DEVDOCK_THEME' => $settingObject->theme,
			'DEVDOCK_SHOW_BRANCH' => $settingObject->showBranch ? 'true' : 'false',
			'APP_LOCALE' => $settingObject->language,
		];

		if ($settingObject->jiraToken !== null) {
			$values['JIRA_TOKEN'] = $settingObject->jiraToken;
		}

		if ($settingObject->bitbucketToken !== null) {
			$values['BITBUCKET_TOKEN'] = $settingObject->bitbucketToken;
		}

		$this->write($values);

		return $this->get();
	}

	/**
	 * Puts the values in .env and throws away the config cache, so both the
	 * pages and `devdock:fetch` read the same settings.
	 *
	 * @param  array<string, string>  $values
	 */
	private function write(array $values): void
	{
		$path = base_path('.env');
		$contents = (string) file_get_contents($path);

		foreach ($values as $key => $value) {
			$contents = $this->replaced($contents, $key, $this->cleaned($key, $value));
		}

		file_put_contents($path, $contents);
		Artisan::call('config:clear');
	}

	private function replaced(string $contents, string $key, string $value): string
	{
		$line = $key . '=' . $this->quoted($value);
		$pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

		if (preg_match($pattern, $contents) === 1) {
			return (string) preg_replace($pattern, $line, $contents, 1);
		}

		return rtrim($contents, "\n") . "\n" . $line . "\n";
	}

	/**
	 * phpdotenv trips over a backslash inside double quotes, so paths go in with
	 * forward slashes. A token stays exactly as it was filled in; nothing may
	 * change about it.
	 */
	private function cleaned(string $key, string $value): string
	{
		return str_ends_with($key, '_PATH') ? str_replace(DIRECTORY_SEPARATOR, '/', $value) : $value;
	}

	private function quoted(string $value): string
	{
		return preg_match('/[\s#]/', $value) === 1
			? '"' . addcslashes($value, '"') . '"'
			: $value;
	}
}
