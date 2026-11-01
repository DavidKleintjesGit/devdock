<div class="dialog-wrap" wire:click.self="closeDatabase">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="database-choose-title">
		<div class="dtop">
			<h2 id="database-choose-title">{{ __('Welke database gebruikt deze kopie?') }}</h2>
			<button class="icon-btn" type="button" wire:click="closeDatabase" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('Op dezelfde server als het project') }} · {{ $database->serverLabel() }}</p>
			@if ($databases === [])
				<div class="empty"><b>{{ __('Geen databases gevonden') }}</b><span>{{ __('De server gaf geen lijst terug met het account uit de .env.') }}</span></div>
			@else
				<div class="list">
					@foreach ($databases as $name)
						<button class="row" type="button" wire:click="useDatabase('{{ $name }}')" wire:key="db-{{ $name }}">
							<span class="sdot {{ $name === $database->database ? 'yours' : 'quiet' }}"></span>
							<span class="grow mono">{{ $database->isSqlite() ? basename($name) : $name }}</span>
							@if ($name === $database->projectDatabase)<span class="label amber">{{ __('van het project') }}</span>@endif
							@if ($name === $database->database)<span class="label teal">{{ __('nu in gebruik') }}</span>@endif
						</button>
					@endforeach
				</div>
			@endif
		</div>
		<div class="dfoot">
			<span class="hint">{{ __('Alleen DB_DATABASE in de .env van de kopie verandert.') }}</span>
			<button class="btn text" type="button" wire:click="closeDatabase">{{ __('Annuleren') }}</button>
		</div>
	</div>
</div>
