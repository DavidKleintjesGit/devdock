<?php

declare(strict_types=1);

namespace Src\Setting\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The settings as they were filled in. A token left empty means 'keep what
 * was there', not 'clear it'.
 */
class SettingObject extends ValueObject
{
	public function __construct(
		public readonly string $atlassianEmail,
		public readonly string $jiraBaseUrl,
		public readonly ?string $jiraToken,
		public readonly string $bitbucketWorkspace,
		public readonly string $bitbucketMe,
		public readonly ?string $bitbucketToken,
		public readonly string $sitesPath,
		public readonly string $sessionsPath,
		public readonly string $corePath,
		public readonly int $refreshSeconds,
		public readonly array $columns,
		public readonly int $doneDays,
		public readonly int $abandonedUpToPrompts,
		public readonly int $largeFromMegabytes,
		public readonly int $oldAfterDays,
		public readonly string $theme,
		public readonly bool $showBranch,
		public readonly string $language,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			atlassianEmail: (string) $data['atlassian_email'],
			jiraBaseUrl: rtrim((string) $data['jira_base_url'], '/'),
			jiraToken: blank($data['jira_token'] ?? null) ? null : trim((string) $data['jira_token']),
			bitbucketWorkspace: (string) $data['bitbucket_workspace'],
			bitbucketMe: (string) $data['bitbucket_me'],
			bitbucketToken: blank($data['bitbucket_token'] ?? null) ? null : trim((string) $data['bitbucket_token']),
			sitesPath: (string) $data['sites_path'],
			sessionsPath: (string) $data['sessions_path'],
			corePath: (string) $data['core_path'],
			refreshSeconds: (int) $data['refresh_seconds'],
			columns: (array) $data['columns'],
			doneDays: (int) $data['done_days'],
			abandonedUpToPrompts: (int) $data['abandoned_up_to'],
			largeFromMegabytes: (int) $data['large_from_mb'],
			oldAfterDays: (int) $data['old_after_days'],
			theme: (string) $data['theme'],
			showBranch: (bool) $data['show_branch'],
			language: (string) $data['language'],
		);
	}
}
