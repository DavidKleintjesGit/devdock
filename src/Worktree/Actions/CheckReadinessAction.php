<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyStateRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Enums\CheckStateEnum;
use Src\Worktree\Enums\ProjectKindEnum;
use Src\Worktree\Enums\SetupCheckEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\ReadinessObject;
use Src\Worktree\Objects\SetupCheckObject;
use Src\Worktree\Objects\WorkingCopyStateObject;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * The checklist a working copy has to pass before an agent or a lens may
 * work in it. Only a working copy DevDock made itself can pass.
 *
 * The migrations are only counted on a thorough check, since that runs
 * artisan in the copy; otherwise the count of the last thorough check holds
 * while the migration files and the database stay the same.
 *
 * @method static ReadinessObject execute(?string $directory, bool $isThorough = false, bool $acceptsRebase = false)
 */
class CheckReadinessAction extends Command
{
	private const MIGRATIONS_KEY = 'setup.migrations.';

	public function __construct(
		public readonly ?string $directory,
		public readonly bool $isThorough = false,
		// a rebase standing open is the work itself, for the turn that finishes it
		public readonly bool $acceptsRebase = false,
	) {}

	public static function forget(string $directory): void
	{
		Cache::forget(self::MIGRATIONS_KEY . md5(self::normalize($directory)));
		Cache::forget(self::MIGRATIONS_KEY . 'testing.' . md5(self::normalize($directory)));
	}

	public static function normalize(string $directory): string
	{
		return strtolower(rtrim(str_replace('\\', '/', $directory), '/'));
	}

	public function handle(
		WorkingCopyStateRepositoryContract $workingCopyStateRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
	): ReadinessObject {
		$worktreeEntity = $this->directory === null
			? null
			: GetWorktreesAction::execute()->first(
				fn (WorktreeEntity $worktreeEntity): bool => self::normalize($worktreeEntity->path) === self::normalize($this->directory),
			);

		$projectEntity = $worktreeEntity === null
			? null
			: GetProjectsAction::execute()->first(fn (ProjectEntity $projectEntity): bool => $projectEntity->name === $worktreeEntity->project);

		if ($worktreeEntity === null || $worktreeEntity->origin !== WorktreeOriginEnum::MADE || $projectEntity === null || ! is_dir($worktreeEntity->path)) {
			return new ReadinessObject(
				directory: (string) $this->directory,
				projectKind: null,
				setupChecks: [new SetupCheckObject(
					SetupCheckEnum::WORKING_COPY,
					CheckStateEnum::OPEN,
					$this->noWorkingCopy($worktreeEntity),
					// a task linked to the project itself or to a folder from elsewhere moves into a copy of its own
					$worktreeEntity?->card !== null && $worktreeEntity->origin !== WorktreeOriginEnum::MADE ? __('eigen werkkopie maken') : null,
				)],
				checkedAt: CarbonImmutable::now(),
			);
		}

		$projectKind = ProjectKindEnum::of($worktreeEntity->path);
		$workingCopyStateObject = $workingCopyStateRepository->read(
			$projectEntity->path,
			$worktreeEntity->path,
			$projectKind,
			array_values((array) config('devdock.worktree.copy')),
			(array) config('devdock.worktree.share'),
			array_values((array) config('devdock.worktree.own_files')),
		);

		$databaseChecks = $projectKind->isLaravel() ? $this->databases($worktreeDatabaseRepository, $projectEntity->path, $worktreeEntity->path) : [];

		// artisan only runs on packages of its own, and the database of the project is never counted
		$canCountMigrations = $workingCopyStateObject->hasVendorAutoload
			&& $workingCopyStateObject->missingVendorPackages === []
			&& ($databaseChecks[0] ?? null)?->isReady() === true;

		$setupChecks = array_filter([
			new SetupCheckObject(SetupCheckEnum::WORKING_COPY, CheckStateEnum::READY, $worktreeEntity->path),
			$this->branch($worktreeEntity, $workingCopyStateObject),
			$this->carriedFiles($workingCopyStateObject),
			$this->ownFilesOutOfGit($workingCopyStateObject),
			$workingCopyStateObject->hasComposer ? $this->phpPackages($workingCopyStateObject) : null,
			$workingCopyStateObject->hasPackageJson ? $this->jsPackages($workingCopyStateObject) : null,
			$projectKind->assetManifest() === null ? null : $this->assets($projectKind, $workingCopyStateObject),
			$projectKind->isLaravel() ? $this->appKey($workingCopyStateObject) : null,
			...$databaseChecks,
			$projectKind->isLaravel() ? $this->migrations($workingCopyStateRepository, $worktreeEntity->path, $canCountMigrations) : null,
			// a test database of its own falls behind like the other one, and then the tests of an agent or lens stop at once
			$projectKind->isLaravel() && ($databaseChecks[1] ?? null)?->isReady() === true && $worktreeDatabaseRepository->describeTesting($projectEntity->path, $worktreeEntity->path) !== null
				? $this->migrations($workingCopyStateRepository, $worktreeEntity->path, $canCountMigrations, isTesting: true)
				: null,
			$projectKind->isLaravel() && $workingCopyStateObject->needsStorageLink ? $this->storageLink($workingCopyStateObject) : null,
			$this->site($workingCopyRepository, $worktreeEntity, $workingCopyStateObject),
		]);

		return new ReadinessObject(
			directory: $worktreeEntity->path,
			projectKind: $projectKind,
			setupChecks: $this->withWaiting(array_values($setupChecks)),
			checkedAt: CarbonImmutable::now(),
		);
	}

	private function noWorkingCopy(?WorktreeEntity $worktreeEntity): string
	{
		return match (true) {
			$this->directory === null => __('Deze taak heeft nog geen werkkopie. Koppel eerst een werkkopie.'),
			$worktreeEntity === null => __('Deze map is geen werkkopie van DevDock. Koppel eerst een werkkopie.'),
			$worktreeEntity->origin === WorktreeOriginEnum::PROJECT => __('Deze taak werkt in de checkout van het project zelf. Een agent werkt alleen in een eigen werkkopie.'),
			$worktreeEntity->origin === WorktreeOriginEnum::ADOPTED => __('Deze map is buiten DevDock gemaakt. Een agent werkt alleen in een werkkopie die DevDock zelf maakte.'),
			default => __('De werkkopie of het project staat niet meer op schijf.'),
		};
	}

	private function branch(WorktreeEntity $worktreeEntity, WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		$followsMain = $workingCopyStateObject->upstream === 'origin/' . $workingCopyStateObject->mainBranch;

		return match (true) {
			$this->acceptsRebase && $workingCopyStateObject->operationInProgress === 'rebase' => new SetupCheckObject(
				SetupCheckEnum::BRANCH,
				CheckStateEnum::READY,
				__('Een rebase van :branch staat open; de agent maakt hem af.', ['branch' => $worktreeEntity->branch]),
			),
			$workingCopyStateObject->currentBranch !== $worktreeEntity->branch => new SetupCheckObject(
				SetupCheckEnum::BRANCH,
				CheckStateEnum::OPEN,
				__('Staat op :current in plaats van :branch. Zet de map terug op de branch van de taak.', [
					'current' => $workingCopyStateObject->currentBranch ?? __('geen branch'),
					'branch' => $worktreeEntity->branch,
				]),
			),
			$workingCopyStateObject->operationInProgress !== null => new SetupCheckObject(
				SetupCheckEnum::BRANCH,
				CheckStateEnum::OPEN,
				__('Een :operation staat halverwege. Maak hem af of breek hem af op de tab Werkkopie.', ['operation' => $workingCopyStateObject->operationInProgress]),
			),
			$followsMain => new SetupCheckObject(
				SetupCheckEnum::BRANCH,
				CheckStateEnum::OPEN,
				__('Volgt :upstream, dus een push zou op de hoofdlijn landen.', ['upstream' => $workingCopyStateObject->upstream]),
				__('upstream weghalen'),
			),
			default => new SetupCheckObject(
				SetupCheckEnum::BRANCH,
				CheckStateEnum::READY,
				$workingCopyStateObject->upstream === null
					? __('Op :branch, nog zonder upstream; de eerste push maakt hem aan.', ['branch' => $worktreeEntity->branch])
					: __('Op :branch, volgt :upstream.', ['branch' => $worktreeEntity->branch, 'upstream' => $workingCopyStateObject->upstream]),
			),
		};
	}

	private function carriedFiles(WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		return $workingCopyStateObject->missingCarried === []
			? new SetupCheckObject(SetupCheckEnum::CARRIED_FILES, CheckStateEnum::READY, __('Alles uit het project staat erin.'))
			: new SetupCheckObject(
				SetupCheckEnum::CARRIED_FILES,
				CheckStateEnum::OPEN,
				__('Ontbreekt: :files', ['files' => implode(', ', $workingCopyStateObject->missingCarried)]),
				__('overnemen'),
			);
	}

	private function ownFilesOutOfGit(WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		return $workingCopyStateObject->missingExcludes === []
			? new SetupCheckObject(SetupCheckEnum::OWN_FILES_OUT_OF_GIT, CheckStateEnum::READY, __('Wat DevDock in de werkkopie zet, staat in de exclude van git en gaat nooit mee in een commit.'))
			: new SetupCheckObject(
				SetupCheckEnum::OWN_FILES_OUT_OF_GIT,
				CheckStateEnum::OPEN,
				__('De exclude van git mist :lines, dus dat zou als wijziging verschijnen.', ['lines' => implode(', ', $workingCopyStateObject->missingExcludes)]),
				__('toevoegen aan de exclude'),
			);
	}

	private function phpPackages(WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		return match (true) {
			$workingCopyStateObject->missingVendorPackages !== [] => new SetupCheckObject(
				SetupCheckEnum::PHP_PACKAGES,
				CheckStateEnum::OPEN,
				__('Geleend, maar deze pakketten ontbreken: :packages.', ['packages' => implode(', ', $workingCopyStateObject->missingVendorPackages)]),
				__('opnieuw lenen'),
			),
			$workingCopyStateObject->isVendorBorrowed && ! $workingCopyStateObject->isComposerLockSame => new SetupCheckObject(
				SetupCheckEnum::PHP_PACKAGES,
				CheckStateEnum::OPEN,
				__('Geleend van het project, maar composer.lock wijkt af.'),
				'composer install',
			),
			! $workingCopyStateObject->hasVendorAutoload => new SetupCheckObject(
				SetupCheckEnum::PHP_PACKAGES,
				CheckStateEnum::OPEN,
				__('vendor ontbreekt.'),
				$workingCopyStateObject->isComposerLockSame ? __('lenen van het project') : 'composer install',
			),
			$workingCopyStateObject->isVendorBorrowed => new SetupCheckObject(SetupCheckEnum::PHP_PACKAGES, CheckStateEnum::READY, __('Geleend van het project, zelfde composer.lock.')),
			default => new SetupCheckObject(SetupCheckEnum::PHP_PACKAGES, CheckStateEnum::READY, __('Eigen installatie.')),
		};
	}

	private function jsPackages(WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		$install = $workingCopyStateObject->hasPackageLock ? 'npm ci' : 'npm install';

		return match (true) {
			$workingCopyStateObject->isNodeModulesBorrowed && ! $workingCopyStateObject->isPackageLockSame => new SetupCheckObject(
				SetupCheckEnum::JS_PACKAGES,
				CheckStateEnum::OPEN,
				__('Geleend van het project, maar package-lock.json wijkt af.'),
				$install,
			),
			! $workingCopyStateObject->hasNodeModules => new SetupCheckObject(
				SetupCheckEnum::JS_PACKAGES,
				CheckStateEnum::OPEN,
				__('node_modules ontbreekt.'),
				$workingCopyStateObject->isPackageLockSame ? __('lenen van het project') : $install,
			),
			$workingCopyStateObject->isNodeModulesBorrowed => new SetupCheckObject(SetupCheckEnum::JS_PACKAGES, CheckStateEnum::READY, __('Geleend van het project, zelfde package-lock.json.')),
			default => new SetupCheckObject(SetupCheckEnum::JS_PACKAGES, CheckStateEnum::READY, __('Eigen installatie.')),
		};
	}

	private function assets(ProjectKindEnum $projectKind, WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		return $workingCopyStateObject->hasAssetManifest
			? new SetupCheckObject(SetupCheckEnum::ASSETS, CheckStateEnum::READY, __(':file staat er.', ['file' => $projectKind->assetManifest()]))
			: new SetupCheckObject(
				SetupCheckEnum::ASSETS,
				CheckStateEnum::OPEN,
				__(':file ontbreekt.', ['file' => $projectKind->assetManifest()]),
				'npm run ' . $projectKind->assetScript(),
			);
	}

	private function appKey(WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		return $workingCopyStateObject->hasAppKey
			? new SetupCheckObject(SetupCheckEnum::APP_KEY, CheckStateEnum::READY, __('Staat in .env.'))
			: new SetupCheckObject(SetupCheckEnum::APP_KEY, CheckStateEnum::OPEN, __('APP_KEY is leeg in .env.'), 'php artisan key:generate');
	}

	/**
	 * @return list<SetupCheckObject>
	 */
	private function databases(WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository, string $repositoryPath, string $path): array
	{
		$databaseObject = $worktreeDatabaseRepository->describe($repositoryPath, $path);
		$testDatabaseObject = $worktreeDatabaseRepository->describeTesting($repositoryPath, $path);

		return [
			$databaseObject->isShared
				? new SetupCheckObject(
					SetupCheckEnum::OWN_DATABASE,
					CheckStateEnum::OPEN,
					__('Deelt :database met het project. Een test of een lens die schrijft, zou jouw data veranderen.', ['database' => $databaseObject->database]),
					__('kopiëren naar een eigen'),
				)
				: new SetupCheckObject(SetupCheckEnum::OWN_DATABASE, CheckStateEnum::READY, (string) $databaseObject->database),
			match (true) {
				$testDatabaseObject === null => new SetupCheckObject(SetupCheckEnum::OWN_TEST_DATABASE, CheckStateEnum::READY, __('De tests draaien op sqlite in deze map.')),
				$testDatabaseObject->isShared => new SetupCheckObject(
					SetupCheckEnum::OWN_TEST_DATABASE,
					CheckStateEnum::OPEN,
					__('Deelt :database met het project; tests van twee werkkopieën zouden elkaar leeghalen.', ['database' => $testDatabaseObject->database]),
					__('een eigen maken'),
				),
				default => new SetupCheckObject(SetupCheckEnum::OWN_TEST_DATABASE, CheckStateEnum::READY, (string) $testDatabaseObject->database),
			},
		];
	}

	private function migrations(WorkingCopyStateRepositoryContract $workingCopyStateRepository, string $path, bool $canCount, bool $isTesting = false): SetupCheckObject
	{
		$key = self::MIGRATIONS_KEY . ($isTesting ? 'testing.' : '') . md5(self::normalize($path));
		$fingerprint = $this->migrationsFingerprint($path, $isTesting ? '.env.testing' : '.env');
		$setupCheck = $isTesting ? SetupCheckEnum::TEST_MIGRATIONS : SetupCheckEnum::MIGRATIONS;
		$kept = Cache::get($key);

		$isKept = is_array($kept) && ($kept['fingerprint'] ?? null) === $fingerprint && ! $this->isThorough;
		$pending = $isKept ? ($kept['pending'] ?? null) : null;
		$failure = $isKept ? ($kept['failure'] ?? null) : null;

		if (! $isKept && $this->isThorough && $canCount) {
			$migrationCountObject = $workingCopyStateRepository->countPendingMigrations($path, $isTesting);
			$pending = $migrationCountObject->pending;
			$failure = $migrationCountObject->failure;
			Cache::forever($key, ['fingerprint' => $fingerprint, 'pending' => $pending, 'failure' => $failure]);
		}

		return match (true) {
			$failure !== null => new SetupCheckObject($setupCheck, CheckStateEnum::OPEN, __('Artisan start niet in deze werkkopie: :failure', ['failure' => $failure])),
			$pending === null => new SetupCheckObject($setupCheck, CheckStateEnum::UNCHECKED, __('Nog niet nagekeken. DevDock kijkt het na voor een agent of lens begint.')),
			$pending === 0 => new SetupCheckObject($setupCheck, CheckStateEnum::READY, __('Alle migraties hebben gedraaid.')),
			default => new SetupCheckObject(
				$setupCheck,
				CheckStateEnum::OPEN,
				trans_choice(':count migratie heeft nog niet gedraaid.|:count migraties hebben nog niet gedraaid.', $pending, ['count' => $pending]),
				$isTesting ? 'php artisan migrate --env=testing' : 'php artisan migrate',
			),
		};
	}

	/**
	 * The migration files and the database they run on: when either changes,
	 * the count of the last thorough check no longer holds.
	 */
	private function migrationsFingerprint(string $path, string $envFile): string
	{
		$files = array_map('basename', glob($path . '/database/migrations/*.php') ?: []);
		$environment = is_file($path . '/' . $envFile) ? md5_file($path . '/' . $envFile) : '';

		return md5(implode('|', $files) . '|' . $environment);
	}

	private function storageLink(WorkingCopyStateObject $workingCopyStateObject): SetupCheckObject
	{
		return $workingCopyStateObject->hasStorageLink
			? new SetupCheckObject(SetupCheckEnum::STORAGE_LINK, CheckStateEnum::READY, __('public/storage wijst naar storage/app/public.'))
			: new SetupCheckObject(SetupCheckEnum::STORAGE_LINK, CheckStateEnum::OPEN, __('public/storage ontbreekt.'), 'php artisan storage:link');
	}

	private function site(WorkingCopyRepositoryContract $workingCopyRepository, WorktreeEntity $worktreeEntity, WorkingCopyStateObject $workingCopyStateObject): ?SetupCheckObject
	{
		$isPublished = $workingCopyRepository->isPublished((string) $worktreeEntity->site);

		// without Herd there is no site to give, and nothing is held up for it
		if ($isPublished === null) {
			return null;
		}

		$isPublished = $isPublished && $worktreeEntity->site !== null;

		$pointsHere = $workingCopyStateObject->appUrl === null || rtrim($workingCopyStateObject->appUrl, '/') === 'http://' . $worktreeEntity->site;

		return $isPublished && $pointsHere
			? new SetupCheckObject(SetupCheckEnum::SITE, CheckStateEnum::READY, __(':site, en APP_URL wijst ernaar.', ['site' => $worktreeEntity->site]))
			: new SetupCheckObject(
				SetupCheckEnum::SITE,
				CheckStateEnum::OPEN,
				$isPublished ? __('APP_URL wijst nog naar :url.', ['url' => $workingCopyStateObject->appUrl]) : __('Herd kent deze werkkopie nog niet.'),
				__('aanmelden bij Herd'),
			);
	}

	/**
	 * Marks a point that cannot be judged or repaired before another one is
	 * ready as waiting for it.
	 *
	 * @param  list<SetupCheckObject>  $setupChecks
	 * @return list<SetupCheckObject>
	 */
	private function withWaiting(array $setupChecks): array
	{
		$notReady = collect($setupChecks)
			->reject(fn (SetupCheckObject $setupCheckObject): bool => $setupCheckObject->isReady())
			->map(fn (SetupCheckObject $setupCheckObject): SetupCheckEnum => $setupCheckObject->setupCheck);

		return array_map(function (SetupCheckObject $setupCheckObject) use ($notReady): SetupCheckObject {
			$blocking = array_values(array_filter(
				$setupCheckObject->setupCheck->waitsFor(),
				fn (SetupCheckEnum $setupCheck): bool => $notReady->contains($setupCheck),
			));

			if ($blocking === [] || $setupCheckObject->isReady()) {
				return $setupCheckObject;
			}

			return new SetupCheckObject(
				$setupCheckObject->setupCheck,
				CheckStateEnum::WAITING,
				__('Wacht op :points.', ['points' => implode(', ', array_map(fn (SetupCheckEnum $setupCheck): string => mb_strtolower($setupCheck->label()), $blocking))]),
				$setupCheckObject->repair,
			);
		}, $setupChecks);
	}
}
