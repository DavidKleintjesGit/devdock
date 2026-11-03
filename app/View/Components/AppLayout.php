<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Illuminate\View\View;
use Src\Board\Actions\GetBoardAction;
use Src\Board\Objects\CardObject;
use Src\Library\Actions\GetLibrariesAction;
use Src\Library\Actions\GetStandingsAction;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\StandingObject;
use Src\Session\Actions\GetSessionTraitsAction;
use Src\Session\Enums\SessionTraitEnum;

class AppLayout extends Component
{
	/**
	 * The signals in the side bar are worth a short wait, not a full board
	 * on every page.
	 */
	private const SIGNAL_SECONDS = 30;

	public function __construct(public ?string $title = null)
	{
	}

	public function render(): View
	{
		return view('layouts.app', [
			'navigation' => $this->navigation(),
		]);
	}

	/**
	 * Every page in the side bar, with what it has to say without opening it.
	 *
	 * @return array<string, array{label: string, icon: string, pill: ?int, count: ?int, flag: ?string}>
	 */
	private function navigation(): array
	{
		$signals = Cache::remember('navigation.signals', self::SIGNAL_SECONDS, fn () => [
			'yours' => collect(GetBoardAction::execute())->filter(fn (CardObject $cardObject) => $cardObject->isYourTurn())->count(),
			'sessions' => $this->sessionsThatCanGo(),
			'behind' => $this->projectsBehind(),
		]);

		return [
			'board' => ['label' => 'Bord', 'icon' => 'bord', 'pill' => $signals['yours'] ?: null, 'count' => null, 'flag' => null],
			'sessions' => ['label' => 'Sessies', 'icon' => 'sessies', 'pill' => null, 'count' => $signals['sessions'] ?: null, 'flag' => null],
			'workflows' => ['label' => 'Workflows', 'icon' => 'workflows', 'pill' => null, 'count' => null, 'flag' => null],
			'rules' => ['label' => 'Regels', 'icon' => 'regels', 'pill' => null, 'count' => null, 'flag' => $signals['behind'] > 0
				? trans_choice(':count project loopt achter op een bibliotheek|:count projecten lopen achter op een bibliotheek', $signals['behind'], ['count' => $signals['behind']])
				: null],
			'skills' => ['label' => 'Skills', 'icon' => 'skills', 'pill' => null, 'count' => null, 'flag' => null],
			'access' => ['label' => 'Toegang', 'icon' => 'lock', 'pill' => null, 'count' => null, 'flag' => null],
		];
	}

	/**
	 * Sessions nothing pleads for and at least three things point away from.
	 */
	private function sessionsThatCanGo(): int
	{
		return collect(GetSessionTraitsAction::execute())
			->filter(fn (array $traits) => ! collect($traits)->contains(fn (SessionTraitEnum $trait) => $trait->protects())
				&& count($traits) >= 3)
			->count();
	}

	private function projectsBehind(): int
	{
		return collect(GetLibrariesAction::execute())
			->flatMap(fn (LibraryEntity $libraryEntity) => GetStandingsAction::execute($libraryEntity))
			->filter(fn (StandingObject $standingObject) => $standingObject->standing->deviates())
			->pluck('project')
			->unique()
			->count();
	}
}
