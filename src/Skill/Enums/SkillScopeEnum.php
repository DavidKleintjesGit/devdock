<?php

declare(strict_types=1);

namespace Src\Skill\Enums;

enum SkillScopeEnum: string
{
	case GLOBALLY = 'Globaal';
	case PROJECT = 'Van een project';
	case PLUGIN = 'Uit plugins';

	public function label(): string
	{
		return __($this->value);
	}

	public function note(): ?string
	{
		return match ($this) {
			self::GLOBALLY => __('Geldt in elk project'),
			self::PLUGIN => __('Alleen-lezen'),
			self::PROJECT => null,
		};
	}

	public function emptyHint(): string
	{
		return $this === self::PLUGIN
			? __('Er is geen plugin met skills geïnstalleerd.')
			: __('Maak er een aan met de knop rechtsboven.');
	}

	public function isEditable(): bool
	{
		return $this !== self::PLUGIN;
	}
}
