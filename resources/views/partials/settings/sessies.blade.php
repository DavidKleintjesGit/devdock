<header>
	<h3>{{ __('Sessies') }}</h3>
	<p>{{ __('Wanneer een sessie een kenmerk krijgt op de pagina Sessies.') }}</p>
</header>

<section>
	<div class="setlist">
		<div class="set stack">
			<span class="txt"><b>{{ __('Afgebroken tot') }}</b><small>{{ __('Voor de kenmerken Afgebroken en Valse start.') }}</small></span>
			<span class="seg" role="group" aria-label="{{ __('Afgebroken tot') }}">
				@foreach ([0, 1, 2, 3, 5] as $prompts)
					<button type="button" aria-pressed="{{ $abandonedUpTo === $prompts ? 'true' : 'false' }}" wire:click="$set('abandonedUpTo', {{ $prompts }})">{{ trans_choice(':count bericht|:count berichten', $prompts, ['count' => $prompts]) }}</button>
				@endforeach
			</span>
		</div>
		<div class="set stack">
			<span class="txt"><b>{{ __('Groot vanaf') }}</b><small>{{ __('Voor het kenmerk Groot.') }}</small></span>
			<span class="seg" role="group" aria-label="{{ __('Groot vanaf') }}">
				@foreach ([5, 10, 15, 25, 50] as $megabytes)
					<button type="button" aria-pressed="{{ $largeFromMegabytes === $megabytes ? 'true' : 'false' }}" wire:click="$set('largeFromMegabytes', {{ $megabytes }})">{{ $megabytes }} MB</button>
				@endforeach
			</span>
		</div>
		<div class="set stack">
			<span class="txt"><b>{{ __('Oud na') }}</b><small>{{ __('De eerste stap onder Tijd.') }}</small></span>
			<span class="seg" role="group" aria-label="{{ __('Oud na') }}">
				@foreach ([7, 14, 30] as $days)
					<button type="button" aria-pressed="{{ $oldAfterDays === $days ? 'true' : 'false' }}" wire:click="$set('oldAfterDays', {{ $days }})">{{ trans_choice(':count dag|:count dagen', $days, ['count' => $days]) }}</button>
				@endforeach
			</span>
		</div>
	</div>
</section>
