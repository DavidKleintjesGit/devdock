<div @class(['statusbox', 'running' => $running]) @if ($running) wire:poll.2s @endif>
	<div class="statusrow">
		<span class="dot {{ $connected === 4 ? 'green' : 'amber' }}"></span>
		<span class="k">{{ __('Koppelingen') }}</span>
		<span class="v">{{ __(':done van :total', ['done' => $connected, 'total' => 4]) }}</span>
		<span class="slot"></span>
	</div>
	@if ($running)
		<button class="statusrow" type="button" disabled>
			<span class="dot amber pulse"></span>
			<span class="k">{{ __('Ophalen') }}</span>
			<span class="v">{{ $run->source ?? '…' }}</span>
			<span class="slot"></span>
		</button>
	@else
		<button class="statusrow" type="button" wire:click="fetchNow" title="{{ __('Nu ophalen') }}">
			<span class="dot"></span>
			<span class="k">{{ __('Bijgewerkt') }}</span>
			<span class="v">{{ $updated ?? __('nooit') }}</span>
			<span class="slot"><x-icon name="refresh" small/></span>
		</button>
	@endif
</div>
