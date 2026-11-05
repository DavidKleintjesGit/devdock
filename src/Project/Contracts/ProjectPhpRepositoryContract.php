<?php

declare(strict_types=1);

namespace Src\Project\Contracts;

interface ProjectPhpRepositoryContract
{
	/**
	 * The PHP binary that runs the code in this folder: the lowest PHP of Herd
	 * that meets the requirement in its composer.json, and DevDock's own PHP
	 * when there is no requirement or no Herd PHP meets it.
	 */
	public function binaryFor(string $path): string;

	/**
	 * The version of that PHP as major.minor, or null when it is DevDock's own.
	 */
	public function versionFor(string $path): ?string;

	/**
	 * Composer with these arguments, run by the PHP of this folder. On Windows
	 * it leaves out the extensions only Linux has, such as pcntl for Horizon.
	 *
	 * @param  list<string>  $arguments
	 * @return list<string>
	 */
	public function composerCommand(string $path, array $arguments): array;

	/**
	 * Has Herd serve a site with the PHP of its folder when that is not the
	 * PHP Herd serves by default.
	 */
	public function isolateSite(string $path, string $site): void;
}
