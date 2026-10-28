<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Src\Fetch\Actions\CountConnectedSourcesAction;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;

class AppLayout extends Component
{
	/**
	 * The number of sources DevDock knows: Jira, Bitbucket, the sites folder and
	 * the sessions folder.
	 */
	private const SOURCES = 4;

	public function __construct(public ?string $title = null)
	{
	}

	public function render(): View
	{
		return view('layouts.app', [
			'navigation' => $this->navigation(),
			'sources' => $this->sources(),
			'otherLocale' => app()->getLocale() === 'en' ? 'nl' : 'en',
		]);
	}

	/**
	 * @return array<string, array{label: string, icon: string}>
	 */
	private function navigation(): array
	{
		return [
			'board' => ['label' => 'Bord', 'icon' => 'bord'],
			'sessions' => ['label' => 'Sessies', 'icon' => 'sessies'],
			'workflows' => ['label' => 'Workflows', 'icon' => 'workflows'],
			'rules' => ['label' => 'Regels', 'icon' => 'regels'],
			'skills' => ['label' => 'Skills', 'icon' => 'skills'],
		];
	}

	/**
	 * How many of the sources can be reached, and when something was last
	 * fetched.
	 *
	 * @return array{connected: int, total: int, tone: string, updated: string}
	 */
	private function sources(): array
	{
		$connected = CountConnectedSourcesAction::execute();

		$scannedAt = collect(GetProjectsAction::execute())
			->map(fn (ProjectEntity $projectEntity) => $projectEntity->scannedAt)
			->filter()
			->max();

		return [
			'connected' => $connected,
			'total' => self::SOURCES,
			'tone' => $connected === self::SOURCES ? 'green' : 'amber',
			'updated' => $scannedAt?->format('H:i') ?? __('nooit'),
		];
	}
}
