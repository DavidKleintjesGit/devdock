@props(['options', 'selected', 'total', 'withoutProject' => false, 'withoutProjectCount' => null, 'query' => '', 'queryProperty' => null, 'toggle', 'clear', 'work' => 'selectWorkProjects'])

{{--
	Het openen en het zoeken gebeuren hier in de browser. Livewire vervangt bij
	elke keuze de lade, en een <details> die van de server komt staat dan weer
	dicht; Alpine houdt die stand vast. Het zoeken gaat om dezelfde reden niet
	naar de server: dan was elke toetsaanslag een nieuw bord.

	Geen keuze ($selected is null) toont alles; een lege keuze toont alleen wat
	zonder project is, of niets.
--}}
<details
	class="menu picker"
	x-data="{
		open: false,
		zoek: '',
		get treffers() {
			const zoek = this.zoek.trim().toLowerCase();

			return [...$el.querySelectorAll('[data-naam]')]
				.filter((optie) => optie.dataset.naam.includes(zoek))
				.length;
		},
	}"
	:open="open"
	@click.outside="open = false"
	@keydown.escape.window="open = false"
>
	<summary class="picker-btn" :aria-expanded="open" @click.prevent="open = ! open">
		<span class="k">{{ __('Project') }}</span>
		<b>{{ $selected === null ? __('Alles') : ($selected === [] ? ($withoutProject ? __('Zonder project') : __('Niets')) : implode(', ', $selected)) }}</b>
		<span class="n">{{ $selected === null ? $total : count($selected) }}</span>
		<x-icon name="down" small/>
	</summary>

	<div class="menu-pop picker-pop">
		<div class="picker-top">
			<label class="picker-search">
				<x-icon name="search"/>
				<span class="sr">{{ __('Zoek een project') }}</span>
				<input type="search" x-model="zoek" placeholder="{{ __('Zoek een project') }}" autocomplete="off">
			</label>
			<button class="btn text sm" type="button" wire:click="deselectProjects">{{ __('Alles uitzetten') }}</button>
		</div>

		<ul class="picker-list" role="listbox" aria-multiselectable="true">
			<li class="popt" role="option" aria-selected="{{ $selected === null ? 'true' : 'false' }}" wire:click="{{ $clear }}">
				<span class="chk">@if ($selected === null)<x-icon name="check" small/>@endif</span>
				<span class="nm">{{ __('Alles, ook prive') }}</span>
				<span class="n">{{ $total }}</span>
			</li>
			<li class="popt" role="option" wire:click="{{ $work }}">
				<span class="chk"></span>
				<span class="nm">{{ __('Alleen mijn werk') }}</span>
			</li>

			@if ($withoutProjectCount !== null)
				<li class="psep" role="presentation" x-show="zoek.trim() === ''"></li>
				<li class="popt" role="option" aria-selected="{{ $selected !== null && $withoutProject ? 'true' : 'false' }}" wire:click="toggleWithoutProject" x-show="zoek.trim() === ''">
					<span class="chk">@if ($selected !== null && $withoutProject)<x-icon name="check" small/>@endif</span>
					<span class="nm">{{ __('Zonder project') }}</span>
					<span class="n">{{ $withoutProjectCount }}</span>
				</li>
			@endif

			@foreach ($options as [$name, $count, $isWork])
				@if (! $isWork && ($loop->first || $options[$loop->index - 1][2]))
					<li class="psep" role="presentation" x-show="zoek.trim() === ''">{{ __('Buiten de workspace') }}</li>
				@endif
				<li
					class="popt"
					role="option"
					data-naam="{{ strtolower($name) }}"
					x-show="$el.dataset.naam.includes(zoek.trim().toLowerCase())"
					aria-selected="{{ in_array($name, $selected ?? [], true) ? 'true' : 'false' }}"
					wire:click="{{ $toggle }}('{{ $name }}')"
					wire:key="pick-{{ $name }}"
				>
					<span class="chk">@if (in_array($name, $selected ?? [], true))<x-icon name="check" small/>@endif</span>
					<span class="nm">{{ $name }}</span>
					<button class="only" type="button" wire:click.stop="onlyProject('{{ $name }}')">{{ __('Alleen dit') }}</button>
					<span class="n">{{ $count }}</span>
				</li>
			@endforeach

			<li class="pempty" role="presentation" x-show="treffers === 0" x-cloak>
				{{ __('Geen project gevonden') }}
			</li>
		</ul>
	</div>
</details>
