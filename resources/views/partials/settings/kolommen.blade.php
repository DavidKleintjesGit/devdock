<div class="panelhead"><h3>{{ __('Kolommen') }}</h3></div>

@foreach ($allColumns as $column)
	<div class="set">
		<div class="txt"><span class="lbl"><span class="dot {{ $column->tone() }}"></span> {{ $column->label() }}</span></div>
		<button class="toggle" type="button" aria-pressed="{{ in_array($column->value, $columns, true) ? 'true' : 'false' }}" wire:click="toggleColumn('{{ $column->value }}')" aria-label="{{ $column->label() }}"></button>
	</div>
@endforeach

<div class="set">
	<div class="txt"><label class="lbl" for="done">{{ __('Klaar-kolom kijkt terug') }}</label></div>
	<select id="done" class="select" wire:model="doneDays">
		@foreach ([7, 14, 30] as $value)
			<option value="{{ $value }}">{{ __(':count dagen', ['count' => $value]) }}</option>
		@endforeach
	</select>
</div>
