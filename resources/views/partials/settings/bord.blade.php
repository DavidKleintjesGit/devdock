<header>
	<h3>{{ __('Bord') }}</h3>
	<p>{{ __('Elke kolom is een stap van de werkwijze. Kies welke je ziet.') }}</p>
</header>

<section>
	<h4>{{ __('Kolommen') }}</h4>
	<div class="setlist">
		<div class="set">
			<span class="dot faint"></span>
			<span class="txt"><b>{{ __('Later (backlog)') }}</b></span>
			<button class="switch" type="button" role="switch" aria-checked="{{ $backlogHidden ? 'false' : 'true' }}" wire:click="$toggle('backlogHidden')" aria-label="{{ __('Later (backlog)') }}"></button>
		</div>
		@foreach ($allColumns as $column)
			<div class="set" wire:key="setcol-{{ $column->value }}">
				<span class="dot {{ $column->tone() }}"></span>
				<span class="txt"><b>{{ $column->label() }}</b></span>
				<button class="switch" type="button" role="switch" aria-checked="{{ in_array($column->value, $columns, true) ? 'true' : 'false' }}" wire:click="toggleColumn('{{ $column->value }}')" aria-label="{{ $column->label() }}"></button>
			</div>
		@endforeach
	</div>
</section>

<section>
	<h4>{{ __('Op het bord') }}</h4>
	<div class="setlist">
		<div class="set">
			<span class="txt"><b>{{ __('Klaar kijkt terug') }}</b></span>
			<span class="seg" role="group" aria-label="{{ __('Klaar kijkt terug') }}">
				@foreach ([7, 14, 30] as $days)
					<button type="button" aria-pressed="{{ $doneDays === $days ? 'true' : 'false' }}" wire:click="$set('doneDays', {{ $days }})">{{ trans_choice(':count dag|:count dagen', $days, ['count' => $days]) }}</button>
				@endforeach
			</span>
		</div>
		<div class="set">
			<span class="txt"><b>{{ __('Backlog standaard verborgen') }}</b></span>
			<button class="switch" type="button" role="switch" aria-checked="{{ $backlogHidden ? 'true' : 'false' }}" wire:click="$toggle('backlogHidden')" aria-label="{{ __('Backlog standaard verborgen') }}"></button>
		</div>
		<div class="set">
			<span class="txt"><b>{{ __('Stilgevallen na') }}</b><small>{{ __('Geen commit, agentbericht, reviewronde of reactie op de pull request. Geldt voor Bouwen, Review en Pull request.') }}</small></span>
			<span class="seg" role="group" aria-label="{{ __('Stilgevallen na') }}">
				@foreach ([3, 5, 7, 14] as $days)
					<button type="button" aria-pressed="{{ $staleDays === $days ? 'true' : 'false' }}" wire:click="$set('staleDays', {{ $days }})">{{ trans_choice(':count dag|:count dagen', $days, ['count' => $days]) }}</button>
				@endforeach
			</span>
		</div>
	</div>
</section>
