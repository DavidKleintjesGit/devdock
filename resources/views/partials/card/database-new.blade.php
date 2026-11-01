<div class="dialog-wrap" wire:click.self="closeDatabase">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="database-title">
		<div class="dtop">
			<h2 id="database-title">{{ __('Eigen database voor :task', ['task' => $card->ticketKey ?? $card->title]) }}</h2>
			<button class="icon-btn" type="button" wire:click="closeDatabase" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('Zo raakt wat deze taak doet de database van het project niet meer.') }}</p>

			<div class="field">
				<span class="counter-row"><label for="database-name" style="font-size:13px;font-weight:500;color:var(--fg)">{{ __('Naam') }}</label><span>{{ __('voorstel: projectdatabase + ticket') }}</span></span>
				<input id="database-name" class="input mono" type="text" wire:model.live.debounce.400ms="databaseName" autocomplete="off">
				<span class="hint">{{ __('Op dezelfde server als het project') }} · {{ $database->serverLabel() }}</span>
			</div>

			<div class="field">
				<span class="label-text">{{ __('Vullen met') }}</span>
				<div class="choices">
					@foreach ($fills as $fill)
						<label class="choice" wire:key="fill-{{ $fill->value }}">
							<input type="radio" name="database-fill" value="{{ $fill->value }}" wire:model.live="databaseFill">
							<span class="radio"></span>
							<span><b>{{ $fill->label((string) ($database->isSqlite() ? basename((string) $database->projectDatabase) : $database->projectDatabase)) }}</b><small>{{ $fill->explanation() }}</small></span>
						</label>
					@endforeach
				</div>
			</div>

			<div class="field">
				<span class="label-text">{{ __('Wat er in de werkkopie verandert') }}</span>
				<pre class="code-block">.env  DB_DATABASE={{ $database->isSqlite() ? 'database/' . $databaseName . '.sqlite' : $databaseName }}</pre>
				<span class="hint">{{ __('.env.testing blijft zoals het is. De database gaat weg als je de werkkopie opruimt.') }}</span>
			</div>
		</div>
		<div class="dfoot">
			<span class="hint"></span>
			<button class="btn text" type="button" wire:click="closeDatabase">{{ __('Annuleren') }}</button>
			<x-busy-button action="createDatabase" class="btn primary" :busy="__('Aanmaken...')">{{ __('Aanmaken') }}</x-busy-button>
		</div>
	</div>
</div>
