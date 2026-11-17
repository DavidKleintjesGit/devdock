<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * How the branch of a working copy stands against its own branch on origin.
 */
enum OriginStandingEnum: string
{
	case PUSHED = 'pushed';
	case UNPUSHED = 'unpushed';

	/**
	 * What a card in review says about it.
	 */
	public function label(int $unpushedCommits): string
	{
		return match ($this) {
			self::PUSHED => __('Gepusht · nog geen PR'),
			self::UNPUSHED => __('↑:count niet gepusht', ['count' => $unpushedCommits]),
		};
	}

	public function tone(): string
	{
		return match ($this) {
			self::PUSHED => 'blue',
			self::UNPUSHED => 'gray',
		};
	}
}
