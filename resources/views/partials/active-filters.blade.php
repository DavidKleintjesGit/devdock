@if ($chips !== [])
	<div class="activefilters" role="status">
		<span class="k">{{ __('Filters') }}</span>
		@foreach ($chips as $chip)
			<button class="filterchip" type="button" wire:click="clearFilter('{{ $chip['filter'] }}')">
				{{ $chip['label'] }}<x-icon name="x" small/>
			</button>
		@endforeach
		<button class="btn sm" type="button" wire:click="resetFilters">{{ __('Alles wissen') }}</button>
	</div>
@endif
