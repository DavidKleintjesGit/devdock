<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * A group of points on the checklist of a working copy, as the Inrichting subtab shows them.
 */
enum SetupGroupEnum: string
{
	case FOLDER = 'folder';
	case PACKAGES = 'packages';
	case DATABASES = 'databases';
	case SITE = 'site';

	public function label(): string
	{
		return __(match ($this) {
			self::FOLDER => 'Map en branch',
			self::PACKAGES => 'Pakketten',
			self::DATABASES => 'Databases',
			self::SITE => 'Laravel en site',
		});
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::FOLDER => 'De eigen map, op de goede branch, met wat git niet meeneemt.',
			self::PACKAGES => 'Wat de code nodig heeft om te draaien.',
			self::DATABASES => 'Eigen databases, zodat je niemand anders zijn data verandert.',
			self::SITE => 'Wat nodig is om de app in de browser te openen.',
		});
	}

	public static function of(SetupCheckEnum $setupCheck): self
	{
		return match ($setupCheck) {
			SetupCheckEnum::WORKING_COPY, SetupCheckEnum::BRANCH, SetupCheckEnum::CARRIED_FILES, SetupCheckEnum::OWN_FILES_OUT_OF_GIT => self::FOLDER,
			SetupCheckEnum::PHP_PACKAGES, SetupCheckEnum::JS_PACKAGES, SetupCheckEnum::ASSETS => self::PACKAGES,
			SetupCheckEnum::OWN_DATABASE, SetupCheckEnum::OWN_TEST_DATABASE, SetupCheckEnum::MIGRATIONS, SetupCheckEnum::TEST_MIGRATIONS => self::DATABASES,
			SetupCheckEnum::APP_KEY, SetupCheckEnum::STORAGE_LINK, SetupCheckEnum::SITE => self::SITE,
		};
	}
}
