<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Src\Packages\Cqrs\Command;
use Src\Packages\Process\DetachedCommand;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyStateRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\ProjectKindEnum;
use Src\Worktree\Enums\SetupCheckEnum;
use Src\Worktree\Objects\ReadinessObject;
use Src\Worktree\Objects\RepairStateObject;
use Src\Worktree\Objects\SetupCheckObject;
use Src\Worktree\Objects\SiteName;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Repairs the open points of a working copy, one point or all of them, in
 * the order of the checklist. After every repair the list is read again, so a
 * point that waited on it is taken along. It stops at the first repair that
 * fails, with the reason in the state and everything said in the log.
 *
 * This runs in a process of its own: installing packages or copying a
 * database takes minutes.
 *
 * @method static RepairStateObject execute(string $directory, ?SetupCheckEnum $setupCheck = null)
 */
class RepairSetupAction extends Command
{
	public const KEY = 'setup.repair.';

	/**
	 * More repairs than points on the list means one keeps coming back.
	 */
	private const MAX_REPAIRS = 16;

	private const INSTALL_SECONDS = 900;

	private const STEP_SECONDS = 600;

	public function __construct(
		public readonly string $directory,
		public readonly ?SetupCheckEnum $setupCheck = null,
	) {}

	public static function logPath(string $directory): string
	{
		return storage_path('app/setup/' . md5(CheckReadinessAction::normalize($directory)) . '.log');
	}

	public function handle(
		WorkingCopyStateRepositoryContract $workingCopyStateRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
		WorktreeRepositoryContract $worktreeRepository,
	): RepairStateObject {
		$logPath = self::logPath($this->directory);
		File::ensureDirectoryExists(dirname($logPath));
		File::put($logPath, '');

		$failure = null;
		$current = null;

		for ($repair = 0; $repair < self::MAX_REPAIRS && $failure === null; $repair++) {
			$readinessObject = CheckReadinessAction::execute($this->directory, true);
			$setupCheckObject = $this->nextRepair($readinessObject);

			if ($setupCheckObject === null) {
				break;
			}

			$current = $setupCheckObject->setupCheck;
			$this->writeState(true, $current, $setupCheckObject->repair, null);
			File::append($logPath, PHP_EOL . '# ' . $current->label() . ' · ' . $setupCheckObject->repair . PHP_EOL);

			$failure = $this->repair($setupCheckObject->setupCheck, $logPath, $workingCopyStateRepository, $workingCopyRepository, $worktreeDatabaseRepository, $worktreeRepository);
			CheckReadinessAction::forget($this->directory);

			// one point asked for is one repair; the points that waited on it are left for the next
			if ($this->setupCheck !== null) {
				break;
			}
		}

		if ($failure !== null) {
			File::append($logPath, PHP_EOL . $failure . PHP_EOL);
		}

		GetReadinessAction::forget($this->directory);
		$worktreeRepository->forgetStatus($this->directory);

		return $this->writeState(false, $current, null, $failure);
	}

	private function nextRepair(ReadinessObject $readinessObject): ?SetupCheckObject
	{
		foreach ($readinessObject->setupChecks as $setupCheckObject) {
			if ($setupCheckObject->canRepair() && ($this->setupCheck === null || $this->setupCheck === $setupCheckObject->setupCheck)) {
				return $setupCheckObject;
			}
		}

		return null;
	}

	/**
	 * Carries out the repair of one point. Returns why it failed, or null.
	 */
	private function repair(
		SetupCheckEnum $setupCheck,
		string $logPath,
		WorkingCopyStateRepositoryContract $workingCopyStateRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
		WorktreeRepositoryContract $worktreeRepository,
	): ?string {
		$worktreeEntity = GetWorktreesAction::execute()->first(
			fn (WorktreeEntity $worktreeEntity): bool => CheckReadinessAction::normalize($worktreeEntity->path) === CheckReadinessAction::normalize($this->directory),
		);
		$projectPath = $worktreeEntity === null ? null : GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity): bool => $projectEntity->name === $worktreeEntity->project)
			?->path;

		if ($worktreeEntity === null || $projectPath === null) {
			return __('De werkkopie of het project staat niet meer op schijf.');
		}

		$path = $worktreeEntity->path;
		$php = resolve(DetachedCommand::class)->phpBinary();
		$prepare = function () use ($workingCopyRepository, $worktreeRepository, $worktreeEntity, $projectPath): ?string {
			$host = $workingCopyRepository->prepare($projectPath, $worktreeEntity->path, SiteName::for($worktreeEntity->project, $worktreeEntity->branch));
			$worktreeRepository->pinSite($worktreeEntity->worktreeId, $host ?? $worktreeEntity->site);

			return null;
		};

		return match ($setupCheck) {
			SetupCheckEnum::BRANCH => $workingCopyStateRepository->runStep($path, ['git', 'branch', '--unset-upstream'], $logPath, self::STEP_SECONDS),
			SetupCheckEnum::CARRIED_FILES, SetupCheckEnum::SITE => $prepare(),
			SetupCheckEnum::PHP_PACKAGES => $this->installPackages($projectPath, $path, 'vendor', ['composer', 'install', '--no-interaction'], $prepare, $logPath, $workingCopyStateRepository, $workingCopyRepository),
			SetupCheckEnum::JS_PACKAGES => $this->installPackages($projectPath, $path, 'node_modules', is_file($path . '/package-lock.json') ? ['npm', 'ci'] : ['npm', 'install'], $prepare, $logPath, $workingCopyStateRepository, $workingCopyRepository),
			SetupCheckEnum::ASSETS => $workingCopyStateRepository->runStep($path, ['npm', 'run', (string) ProjectKindEnum::of($path)->assetScript()], $logPath, self::STEP_SECONDS),
			SetupCheckEnum::APP_KEY => $workingCopyStateRepository->runStep($path, [$php, 'artisan', 'key:generate', '--force'], $logPath, self::STEP_SECONDS),
			SetupCheckEnum::OWN_DATABASE => CreateWorktreeDatabaseAction::execute(
				$worktreeEntity,
				$worktreeDatabaseRepository->describe($projectPath, $path)->suggestionFor((string) ($worktreeEntity->card ?? basename($worktreeEntity->branch))),
				DatabaseFillEnum::COPY,
			),
			SetupCheckEnum::OWN_TEST_DATABASE => $worktreeDatabaseRepository->giveOwnTestDatabase($projectPath, $path, $worktreeEntity->branch),
			SetupCheckEnum::MIGRATIONS => $worktreeDatabaseRepository->describe($projectPath, $path)->isShared
				? __('DevDock migreert nooit de database van het project.')
				: $workingCopyStateRepository->runStep($path, [$php, 'artisan', 'migrate', '--force'], $logPath, self::STEP_SECONDS),
			SetupCheckEnum::STORAGE_LINK => $workingCopyStateRepository->runStep($path, [$php, 'artisan', 'storage:link'], $logPath, self::STEP_SECONDS),
			SetupCheckEnum::WORKING_COPY => $worktreeEntity->card === null
				? __('Een werkkopie koppel je op de tab Werkkopie.')
				: LinkWorkingCopyAction::execute($worktreeEntity->card, $worktreeEntity->project, $worktreeEntity->branch),
		};
	}

	/**
	 * Borrows the packages again when the lock file matches the project;
	 * otherwise gives back what was borrowed and installs them for this copy.
	 *
	 * @param  list<string>  $install
	 */
	private function installPackages(
		string $projectPath,
		string $path,
		string $folder,
		array $install,
		Closure $prepare,
		string $logPath,
		WorkingCopyStateRepositoryContract $workingCopyStateRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
	): ?string {
		$prepare();

		$isBorrowedWell = collect($workingCopyRepository->getPackages($projectPath, $path))
			->contains(fn (array $package): bool => $package['folder'] === $folder && $package['isBorrowed'] && $package['isSameLock']);

		if ($isBorrowedWell) {
			return null;
		}

		$workingCopyRepository->returnBorrowed($path, $folder);

		return $workingCopyStateRepository->runStep($path, $install, $logPath, self::INSTALL_SECONDS);
	}

	private function writeState(bool $isRunning, ?SetupCheckEnum $setupCheck, ?string $step, ?string $failure): RepairStateObject
	{
		$key = self::KEY . md5(CheckReadinessAction::normalize($this->directory));
		$standing = (array) Cache::get($key, []);

		$data = [
			'is_running' => $isRunning,
			'setup_check' => $setupCheck?->value,
			'step' => $step,
			'started_at' => $standing['started_at'] ?? CarbonImmutable::now()->toIso8601String(),
			'finished_at' => $isRunning ? null : CarbonImmutable::now()->toIso8601String(),
			'failure' => $failure,
			'log_path' => self::logPath($this->directory),
		];

		Cache::forever($key, $data);

		return RepairStateObject::from($data);
	}
}
