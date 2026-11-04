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
		<span class="sub">{{ __('Ronde :number', ['number' => $round->number]) }} · {{ $result->finishedAt?->toDisplayZone()->calendar() }}@if ($result->startedAt && $result->finishedAt) · {{ $result->startedAt->diffForHumans($result->finishedAt, true) }}@endif</span>
	</div>

	<div class="dbody">
		<section class="dsec">
			<h3 class="eyebrow">{{ __('Wat de lens zag') }}</h3>
			@if ($findings !== [])
				<div class="list">
					@foreach ($findings as $finding)
						<div class="finding {{ $finding->isOpen() ? '' : 'set-aside' }}" wire:key="finding-{{ $finding->reviewFindingId->id }}">
							<span class="finding-meta">
								<b class="mono">#{{ $finding->number }}</b>
								<span class="label {{ $finding->status->tone() }}">{{ $finding->status->label() }}</span>
								<span class="hint">{{ $finding->statusNote() }}</span>
							</span>
							@if ($finding->filePath())
								<button class="finding-location" type="button" wire:click="openDiff('{{ $finding->filePath() }}', 'branch')">{{ $finding->location }}</button>
							@elseif ($finding->location)
								<span class="finding-location">{{ $finding->location }}</span>
							@endif
							<p class="finding-text">{{ $finding->text }}</p>
							@if ($finding->reason)
								<p class="hint">{{ __('Reden: :reason', ['reason' => $finding->reason]) }}</p>
							@endif

							@if ($ignoringFinding === $finding->reviewFindingId->id)
								<form class="finding-reason" wire:submit="ignoreFinding">
									<label for="ignore-reason-{{ $finding->reviewFindingId->id }}">{{ __('Waarom negeren?') }}</label>
									<input class="input" id="ignore-reason-{{ $finding->reviewFindingId->id }}" type="text" wire:model="ignoreReason" autofocus>
									@error('ignoreReason')<span class="err">{{ $message }}</span>@enderror
									<span class="finding-actions">
										<button class="btn xs primary" type="submit">{{ __('Negeren') }}</button>
										<button class="btn xs" type="button" wire:click="cancelIgnoringFinding">{{ __('Annuleren') }}</button>
									</span>
								</form>
							@elseif ($finding->isOpen())
								<span class="finding-actions">
									<button class="btn xs" type="button" wire:click="resolveFinding({{ $finding->reviewFindingId->id }})">{{ __('Opgelost') }}</button>
									<button class="btn xs" type="button" wire:click="startIgnoringFinding({{ $finding->reviewFindingId->id }})">{{ __('Negeren') }}</button>
								</span>
							@else
								<span class="finding-actions">
									<button class="btn xs" type="button" wire:click="reopenFinding({{ $finding->reviewFindingId->id }})">{{ __('Heropenen') }}</button>
								</span>
							@endif
						</div>
					@endforeach
				</div>
				<p class="hint">{{ __('De agent krijgt alleen de open bevindingen. De lens kijkt volgende ronde niet meer naar wat opgelost is, en meldt wat genegeerd is niet opnieuw.') }}</p>
			@elseif ($report)
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
