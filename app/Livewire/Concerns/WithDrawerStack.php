<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * The side panel is a stack. Open a session from a task and it comes on top
 * while the task stays underneath, so you can go back instead of navigating
 * away.
 */
trait WithDrawerStack
{
	/** @var array<int, array{type: string, id: string, tab: string}> */
	public array $drawerStack = [];

	public function openDrawer(string $type, string $id, string $tab = 'Overzicht'): void
	{
		$this->drawerStack[] = ['type' => $type, 'id' => $id, 'tab' => $tab];
		$this->closeActions();
	}

	/**
	 * A panel you open from the page itself starts a new path.
	 */
	public function replaceDrawer(string $type, string $id, string $tab = 'Overzicht'): void
	{
		$this->drawerStack = [];
		$this->openDrawer($type, $id, $tab);
	}

	public function back(): void
	{
		array_pop($this->drawerStack);
		$this->closeActions();
	}

	public function closeDrawer(): void
	{
		$this->drawerStack = [];
		$this->closeActions();
	}

	public function setDrawerTab(string $tab): void
	{
		if ($this->drawerStack !== []) {
			$this->drawerStack[array_key_last($this->drawerStack)]['tab'] = $tab;
		}

		// the url keeps it, so a refresh leaves you where you were
		if (property_exists($this, 'openTab')) {
			$this->openTab = $tab;
		}
	}

	/** @return array{type: string, id: string, tab: string}|null */
	public function currentDrawer(): ?array
	{
		return $this->drawerStack === [] ? null : $this->drawerStack[array_key_last($this->drawerStack)];
	}

	/** @return array{type: string, id: string, tab: string}|null */
	public function previousDrawer(): ?array
	{
		return count($this->drawerStack) < 2 ? null : $this->drawerStack[count($this->drawerStack) - 2];
	}

	public function drawerTab(): string
	{
		return $this->currentDrawer()['tab'] ?? 'Overzicht';
	}

	/**
	 * The menu of a panel closes as soon as you go somewhere. Components without
	 * such a menu do nothing here.
	 */
	protected function closeActions(): void
	{
		if (property_exists($this, 'actionsOpen')) {
			$this->actionsOpen = false;
		}

		if (property_exists($this, 'confirmingDelete')) {
			$this->confirmingDelete = false;
		}
	}
}
