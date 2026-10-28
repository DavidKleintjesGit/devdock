<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Lets go of a filter that stands on screen as a chip. A chip pointing at a
 * project goes through the same switch as the picker, so the choice stays
 * the same on every page.
 */
trait WithFilterChips
{
	public function clearFilter(string $name): void
	{
		if (str_starts_with($name, 'project:')) {
			$this->toggleProject(substr($name, 8));

			return;
		}

		if (property_exists($this, $name)) {
			$this->{$name} = '';
		}
	}
}
