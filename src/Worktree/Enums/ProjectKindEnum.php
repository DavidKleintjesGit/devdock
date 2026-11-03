<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * What a project is built with, as far as setting up a working copy goes:
 * it decides which points of the checklist apply and how assets are built.
 */
enum ProjectKindEnum: string
{
	case LARAVEL_VITE = 'laravel_vite';
	case LARAVEL_MIX = 'laravel_mix';
	case LARAVEL = 'laravel';
	case NODE = 'node';
	case STATIC = 'static';

	public static function of(string $path): self
	{
		$composer = is_file($path . '/composer.json') ? (string) file_get_contents($path . '/composer.json') : '';
		$isLaravel = str_contains($composer, 'laravel/framework');
		$hasVite = glob($path . '/vite.config.*') !== [];

		return match (true) {
			$isLaravel && $hasVite => self::LARAVEL_VITE,
			$isLaravel && is_file($path . '/webpack.mix.js') => self::LARAVEL_MIX,
			$isLaravel => self::LARAVEL,
			is_file($path . '/package.json') => self::NODE,
			default => self::STATIC,
		};
	}

	public function label(): string
	{
		return __(match ($this) {
			self::LARAVEL_VITE => 'Laravel met Vite',
			self::LARAVEL_MIX => 'Laravel met Mix',
			self::LARAVEL => 'Laravel',
			self::NODE => 'Node',
			self::STATIC => 'Zonder bouwstap',
		});
	}

	public function isLaravel(): bool
	{
		return in_array($this, [self::LARAVEL_VITE, self::LARAVEL_MIX, self::LARAVEL], true);
	}

	/**
	 * The file a build leaves behind, relative to the working copy; null when
	 * the project builds no assets.
	 */
	public function assetManifest(): ?string
	{
		return match ($this) {
			self::LARAVEL_VITE => 'public/build/manifest.json',
			self::LARAVEL_MIX => 'public/mix-manifest.json',
			default => null,
		};
	}

	/**
	 * The npm script that builds the assets of this kind of project.
	 */
	public function assetScript(): ?string
	{
		return match ($this) {
			self::LARAVEL_VITE => 'build',
			self::LARAVEL_MIX => 'dev',
			default => null,
		};
	}
}
