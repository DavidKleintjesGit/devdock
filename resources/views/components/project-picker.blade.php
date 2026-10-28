@props(['options', 'selected', 'total', 'query', 'queryProperty', 'toggle', 'clear', 'work' => 'selectWorkProjects'])

<details class="menu picker">
	<summary class="picker-btn">
		<span class="k">{{ __('Project') }}</span>
		<b>{{ $selected === [] ? __('Alles') : implode(', ', $selected) }}</b>
		<span class="n">{{ $selected === [] ? $total : count($selected) }}</span>
		<x-icon name="down" small/>
	</summary>

	<div class="menu-pop picker-pop">
		<label class="picker-search">
			<x-icon name="search"/>
			<span class="sr">{{ __('Zoek een project') }}</span>
			<input type="search" wire:model.live.debounce.200ms="{{ $queryProperty }}" placeholder="{{ __('Zoek een project') }}" autocomplete="off">
		</label>

		<ul class="picker-list" role="listbox" aria-multiselectable="true">
			<li class="popt" role="option" aria-selected="{{ $selected === [] ? 'true' : 'false' }}" wire:click="{{ $clear }}">
				<span class="chk">@if ($selected === [])<x-icon name="check" small/>@endif</span>
				<span class="nm">{{ __('Alles, ook prive') }}</span>
				<span class="n">{{ $total }}</span>
			</li>
			<li class="popt" role="option" wire:click="{{ $work }}">
				<span class="chk"></span>
				<span class="nm">{{ __('Alleen mijn werk') }}</span>
			</li>

			@forelse ($options as [$name, $count, $isWork])
				@if (! $isWork && ($loop->first || $options[$loop->index - 1][2]))
					<li class="psep" role="presentation">{{ __('Buiten de workspace') }}</li>
				@endif
				<li class="popt" role="option" aria-selected="{{ in_array($name, $selected, true) ? 'true' : 'false' }}" wire:click="{{ $toggle }}('{{ $name }}')" wire:key="pick-{{ $name }}">
					<span class="chk">@if (in_array($name, $selected, true))<x-icon name="check" small/>@endif</span>
					<span class="nm">{{ $name }}</span>
					<span class="n">{{ $count }}</span>
				</li>
			@empty
				<li class="pempty" role="presentation">{{ __('Geen project gevonden voor “:query”', ['query' => $query]) }}</li>
			@endforelse
		</ul>
	</div>
</details>
