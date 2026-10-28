<?php

declare(strict_types=1);

namespace Src\Setting\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The settings as DevDock reads them now. Of the tokens only their presence
 * is here; the value itself stays in .env.
 */
class SettingEntity extends ValueObject
{
	public function __construct(
		public readonly string $atlassianEmail,
		public readonly string $jiraBaseUrl,
		public readonly bool $hasJiraToken,
		public readonly string $bitbucketWorkspace,
		public readonly string $bitbucketMe,
		public readonly bool $hasBitbucketToken,
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

	/**
	 * The settings back in the shape the form takes them in.
	 */
	public function toFormData(): array
	{
		return [
			'atlassian_email' => $this->atlassianEmail,
			'jira_base_url' => $this->jiraBaseUrl,
			'jira_token' => null,
			'bitbucket_workspace' => $this->bitbucketWorkspace,
			'bitbucket_me' => $this->bitbucketMe,
			'bitbucket_token' => null,
			'sites_path' => $this->sitesPath,
			'sessions_path' => $this->sessionsPath,
			'core_path' => $this->corePath,
			'refresh_seconds' => $this->refreshSeconds,
			'columns' => $this->columns,
			'done_days' => $this->doneDays,
			'abandoned_up_to' => $this->abandonedUpToPrompts,
			'large_from_mb' => $this->largeFromMegabytes,
			'old_after_days' => $this->oldAfterDays,
			'theme' => $this->theme,
			'show_branch' => $this->showBranch,
			'language' => $this->language,
		];
	}
}
