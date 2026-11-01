<div class="nav"
	@if ($running)
		wire:poll.2s="followRun"
	@elseif ($lookEvery > 0)
		wire:poll.{{ $lookEvery }}s="refreshWhenStale"
	@endif
>
	@if ($running)
		<button type="button" disabled>
			<x-icon name="refresh"/>
			<span class="nav-label">{{ __('Ophalen: :source', ['source' => $run->source ?? '…']) }}</span>
			<span class="state"><span class="dot amber pulse"></span></span>
		</button>
	@elseif ($failed !== null)
		<button class="fail" type="button" wire:click="fetchNow" title="{{ __(':source gaf een fout terug; opnieuw proberen', ['source' => $failed]) }}">
			<x-icon name="alert"/>
			<span class="nav-label"><b>{{ __(':source niet bereikbaar', ['source' => $failed]) }}</b><small>{{ __('de rest om :time bijgewerkt', ['time' => $updated ?? __('nooit')]) }}</small></span>
			<span class="state">{{ $connected }}/4</span>
		</button>
	@else
		<button type="button" wire:click="fetchNow" title="{{ __('Nu ophalen') }}">
			<x-icon name="refresh"/>
			<span class="nav-label">{{ __('Bijgewerkt :time', ['time' => $updated ?? __('nooit')]) }}</span>
			<span class="state"><span class="dot {{ $connected === 4 ? 'green' : 'amber' }}"></span>{{ $connected }}/4</span>
		</button>
	@endif
</div>
