<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Src\Access\Actions\GetAccessOverviewAction;
use Src\Access\Enums\AccessCapabilityEnum;

/**
 * Who Claude Code may reach as agent, lens or helper. The page only shows it;
 * the policy itself is set in config/devdock.php.
 */
class Access extends Component
{
	public function render(): View
	{
		return view('livewire.access', [
			'accessOverview' => GetAccessOverviewAction::execute(),
			'capabilities' => AccessCapabilityEnum::cases(),
		]);
	}
}
