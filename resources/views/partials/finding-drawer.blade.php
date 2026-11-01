<aside class="drawer" role="dialog" aria-labelledby="finding-title">
	<div class="dbar">
		@if ($previous)
			<button class="back kind" type="button" wire:click="back"><x-icon name="back" small/>{{ $previous }}</button>
		@else
			<span class="kind">{{ __('Bevinding') }}</span>
		@endif
		<button class="icon-btn" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<span class="meta"><span class="mono">{{ $result->lensName }}</span><span class="label {{ $result->verdict->chipTone() }}">{{ $result->verdict->label() }}</span></span>
		<h2 id="finding-title">{{ $result->summary() }}</h2>
		<span class="sub">{{ __('Ronde :number', ['number' => $round->number]) }} · {{ $result->finishedAt?->calendar() }}@if ($result->startedAt && $result->finishedAt) · {{ $result->startedAt->diffForHumans($result->finishedAt, true) }}@endif</span>
	</div>

	<div class="dbody">
		<section class="dsec">
			<h3 class="eyebrow">{{ __('Wat de lens zag') }}</h3>
			@if ($report)
				<div class="md">{!! $report !!}</div>
			@else
				<p class="muted">{{ __('De lens liet geen rapport achter.') }}</p>
			@endif
		</section>
		<section class="dsec">
			<h3 class="eyebrow">{{ __('Draaide') }}</h3>
			<p class="hint">{{ trans_choice(':count bestand bekeken|:count bestanden bekeken', $result->touchedFiles, ['count' => $result->touchedFiles]) }}{{ $result->toolCalls ? ' · ' . trans_choice(':count tool call|:count tool calls', $result->toolCalls, ['count' => $result->toolCalls]) : '' }}{{ $result->cost ? ' · $' . number_format($result->cost, 2) : '' }}</p>
		</section>
	</div>

	<div class="dfoot">
		<span class="hint">{{ __('Meegeven doe je op de Agent-tab') }}</span>
		<button class="btn primary" type="button" wire:click="back">{{ __('Terug naar de kaart') }}</button>
	</div>
</aside>
