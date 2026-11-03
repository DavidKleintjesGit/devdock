<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * One point of the checklist a working copy has to pass before an agent or a
 * lens may work in it, in the order they are repaired.
 */
enum SetupCheckEnum: string
{
	case WORKING_COPY = 'working_copy';
	case BRANCH = 'branch';
	case CARRIED_FILES = 'carried_files';
	case PHP_PACKAGES = 'php_packages';
	case JS_PACKAGES = 'js_packages';
	case ASSETS = 'assets';
	case APP_KEY = 'app_key';
	case OWN_DATABASE = 'own_database';
	case OWN_TEST_DATABASE = 'own_test_database';
	case MIGRATIONS = 'migrations';
	case STORAGE_LINK = 'storage_link';
	case SITE = 'site';

	public function label(): string
	{
		return __(match ($this) {
			self::WORKING_COPY => 'Werkkopie van DevDock',
			self::BRANCH => 'Branch',
			self::CARRIED_FILES => 'Bestanden uit het project',
			self::PHP_PACKAGES => 'PHP-pakketten',
			self::JS_PACKAGES => 'JS-pakketten',
			self::ASSETS => 'Assets',
			self::APP_KEY => 'App key',
			self::OWN_DATABASE => 'Eigen database',
			self::OWN_TEST_DATABASE => 'Eigen testdatabase',
			self::MIGRATIONS => 'Migraties',
			self::STORAGE_LINK => 'Storage-link',
			self::SITE => 'Site',
		});
	}

	/**
	 * The points that have to be ready before this one can be checked or repaired.
	 *
	 * @return list<self>
	 */
	public function waitsFor(): array
	{
		return match ($this) {
			self::ASSETS => [self::JS_PACKAGES],
			self::APP_KEY, self::STORAGE_LINK => [self::CARRIED_FILES, self::PHP_PACKAGES],
			self::MIGRATIONS => [self::PHP_PACKAGES, self::OWN_DATABASE],
			default => [],
		};
	}
}
