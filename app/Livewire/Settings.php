<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Src\Board\Enums\BoardColumnEnum;
use Src\Fetch\Actions\GetLatestFetchRunAction;
use Src\Fetch\Actions\StartFetchAction;
use Src\Library\Actions\GetLibrariesAction;
use Src\Library\Actions\UpdateLibraryAction;
use Src\Library\Objects\LibraryEntity;
use Src\Project\Actions\GetProjectsAction;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\Rule\Actions\GetRuleFilesAction;
use Src\Session\Actions\GetSessionsAction;
use Src\Setting\Actions\GetSettingsAction;
use Src\Setting\Actions\SaveSettingsAction;
use Src\Setting\Objects\SettingObject;
use Src\Skill\Actions\GetSkillsAction;
use Src\Ticket\Actions\GetTicketsAction;

class Settings extends Component
{
	public bool $open = false;

	public string $section = 'koppelingen';

	public string $atlassianEmail = '';

	public string $jiraToken = '';

	public string $jiraBaseUrl = '';

	public string $bitbucketWorkspace = '';

	public string $bitbucketMe = '';

	public string $bitbucketToken = '';

	public string $claudeToken = '';

	public string $sitesPath = '';

	public string $sessionsPath = '';

	public string $corePath = '';

	public int $refreshSeconds = 0;

	/** @var array<int, string> */
	public array $columns = [];

	public int $doneDays = 14;

	public int $abandonedUpTo = 2;

	public int $largeFromMegabytes = 15;

	public int $oldAfterDays = 14;

	public string $theme = 'system';

	public string $language = 'en';

	public bool $backlogHidden = true;

	public int $staleDays = 5;

	public bool $saved = false;

	public ?int $editingLibrary = null;

	public string $libraryName = '';

	public string $libraryFolder = '';

	public string $librarySkillsFolder = '';

	public ?string $savedAt = null;

	public function mount(): void
	{
		$this->readSettings();
	}

	#[On('open-settings')]
	public function openSettings(?string $section = null): void
	{
		$this->readSettings();
		$this->section = $section ?? 'koppelingen';
		$this->saved = false;
		$this->open = true;
	}

	public function close(): void
	{
		$this->open = false;
		$this->jiraToken = '';
	}

	public function showSection(string $section): void
	{
		$this->section = $section;
		$this->saved = false;
	}

	public function toggleColumn(string $column): void
	{
		$this->columns = in_array($column, $this->columns, true)
			? array_values(array_diff($this->columns, [$column]))
			: [...$this->columns, $column];
	}

	public function save(): void
	{
		SaveSettingsAction::execute(SettingObject::from([
			'atlassian_email' => $this->atlassianEmail,
			'jira_base_url' => $this->jiraBaseUrl,
			'jira_token' => $this->jiraToken,
			'bitbucket_workspace' => $this->bitbucketWorkspace,
			'bitbucket_me' => $this->bitbucketMe,
			'bitbucket_token' => $this->bitbucketToken,
			'claude_token' => $this->claudeToken,
			'sites_path' => $this->sitesPath,
			'sessions_path' => $this->sessionsPath,
			'core_path' => $this->corePath,
			'refresh_seconds' => $this->refreshSeconds,
			'columns' => $this->columns,
			'done_days' => $this->doneDays,
			'abandoned_up_to' => $this->abandonedUpTo,
			'large_from_mb' => $this->largeFromMegabytes,
			'old_after_days' => $this->oldAfterDays,
			'theme' => $this->theme,
			'backlog_hidden' => $this->backlogHidden,
			'stale_days' => $this->staleDays,
			'language' => $this->language,
		]));

		session(['locale' => $this->language]);
		app()->setLocale($this->language);

		$this->jiraToken = '';
		$this->bitbucketToken = '';
		$this->claudeToken = '';
		$this->saved = true;
		$this->savedAt = now()->toDisplayZone()->format('H:i');
	}

	public function editLibrary(int $library): void
	{
		$libraryEntity = collect(GetLibrariesAction::execute())->first(fn (LibraryEntity $item) => $item->id === $library);

		$this->editingLibrary = $libraryEntity?->id;
		$this->libraryName = (string) $libraryEntity?->name;
		$this->libraryFolder = (string) $libraryEntity?->folder;
		$this->librarySkillsFolder = (string) $libraryEntity?->skillsFolder;
	}

	public function saveLibrary(): void
	{
		if ($this->editingLibrary !== null && trim($this->libraryName) !== '') {
			UpdateLibraryAction::execute($this->editingLibrary, $this->libraryName, $this->libraryFolder, $this->librarySkillsFolder);
		}

		$this->editingLibrary = null;
	}

	public function fetchNow(): void
	{
		StartFetchAction::execute();
	}

	/**
	 * The rail on the left: per heading the parts that fall under it.
	 *
	 * @return array<string, array<string, array{0: string, 1: string}>>
	 */
	private function sections(): array
	{
		return [
			'Bronnen' => [
				'koppelingen' => [__('Koppelingen'), 'plug'],
				'mappen' => [__('Mappen'), 'folder'],
				'bibliotheken' => [__('Bibliotheken'), 'library'],
			],
			'Werkbord' => [
				'bord' => [__('Bord'), 'bord'],
				'sessies' => [__('Sessies'), 'sessies'],
			],
			'Dit apparaat' => [
				'weergave' => [__('Weergave'), 'weergave'],
				'over' => [__('Over DevDock'), 'info'],
			],
		];
	}

	private function readSettings(): void
	{
		$settingEntity = GetSettingsAction::execute();

		$this->atlassianEmail = $settingEntity->atlassianEmail;
		$this->jiraBaseUrl = $settingEntity->jiraBaseUrl;
		$this->bitbucketWorkspace = $settingEntity->bitbucketWorkspace;
		$this->bitbucketMe = $settingEntity->bitbucketMe;
		$this->sitesPath = $settingEntity->sitesPath;
		$this->sessionsPath = $settingEntity->sessionsPath;
		$this->corePath = $settingEntity->corePath;
		$this->refreshSeconds = $settingEntity->refreshSeconds;
		$this->columns = $settingEntity->columns;
		$this->doneDays = $settingEntity->doneDays;
		$this->abandonedUpTo = $settingEntity->abandonedUpToPrompts;
		$this->largeFromMegabytes = $settingEntity->largeFromMegabytes;
		$this->oldAfterDays = $settingEntity->oldAfterDays;
		$this->theme = $settingEntity->theme;
		$this->backlogHidden = $settingEntity->backlogHidden;
		$this->staleDays = $settingEntity->staleDays;
		$this->language = $settingEntity->language;
	}

	public function render(): View
	{
		// dicht is het venster een lege houder op elke pagina; wat erin staat
		// wordt pas opgehaald als je het opent
		if (! $this->open) {
			return view('livewire.settings');
		}

		$settingEntity = GetSettingsAction::execute();

		return view('livewire.settings', [
			'sections' => $this->sections(),
			'hasJiraToken' => $settingEntity->hasJiraToken,
			'hasBitbucketToken' => $settingEntity->hasBitbucketToken,
			'hasClaudeToken' => $settingEntity->hasClaudeToken,
			'run' => GetLatestFetchRunAction::execute(),
			'bitbucketRestMinutes' => intdiv((int) config('devdock.bitbucket.refresh_seconds'), 60),
			'allColumns' => BoardColumnEnum::sprint(),
			'libraries' => $this->section === 'bibliotheken' ? GetLibrariesAction::execute() : [],
			'database' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', (string) config('database.connections.' . config('database.default') . '.database')),
			'counts' => [
				'tickets' => count(GetTicketsAction::execute()),
				'pullRequests' => count(GetPullRequestsAction::execute()),
				'projects' => count(GetProjectsAction::execute()),
				'sessions' => count(GetSessionsAction::execute()),
				'rules' => count(GetRuleFilesAction::execute()),
				'skills' => count(GetSkillsAction::execute()),
			],
			'folders' => [
				'sites' => is_dir($this->sitesPath),
				'sessions' => is_dir($this->sessionsPath),
				'core' => is_dir($this->corePath),
			],
		]);
	}
}
