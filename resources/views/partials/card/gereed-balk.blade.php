@if ($repairState?->isRunning)
	<div class="banner amber" style="padding:12px 24px;display:flex;align-items:center;gap:12px" wire:poll.3s>
		<span class="sdot busy" style="box-shadow:none"></span>
		<span class="grow" style="display:flex;flex-direction:column;gap:2px"><b>{{ __('DevDock herstelt de werkkopie') }}@if ($repairState->setupCheck) · {{ $repairState->setupCheck->label() }}@endif</b><span>{{ $repairState->step ?? __('Bezig met beginnen...') }}</span></span>
	</div>
@elseif ($readiness && ! $readiness->isReady())
	<div class="banner amber" style="padding:12px 24px;display:flex;align-items:center;gap:12px">
		<x-icon name="lock" small/>
		<span class="grow" style="display:flex;flex-direction:column;gap:2px"><b>{{ __('De werkkopie is niet gereed · :open van :total punten open', ['open' => $readiness->openCount(), 'total' => $readiness->totalCount()]) }}</b><span>{{ __('De agent en de lenzen wachten tot hij compleet is. Lezen blijft open.') }}</span></span>
		@if ($readiness->canRepairAny())
			<x-busy-button action="repairSetup" class="btn sm" :busy="__('Starten...')">{{ __('Alles herstellen') }}</x-busy-button>
		@endif
		@if ($tab !== 'Werkkopie')
			<button class="btn sm primary" type="button" wire:click="setDrawerTab('Werkkopie')">{{ __('Checklist bekijken') }}</button>
		@endif
	</div>
@elseif (! $worktree && ! $isSetup && $card->project !== null)
	<div class="banner amber" style="padding:12px 24px;display:flex;align-items:center;gap:12px">
		<x-icon name="lock" small/>
		<span class="grow" style="display:flex;flex-direction:column;gap:2px"><b>{{ __('Deze taak heeft nog geen eigen werkkopie') }}</b><span>{{ __('De agent en de lenzen werken alleen in een eigen werkkopie. Lezen blijft open.') }}</span></span>
		@if ($tab !== 'Werkkopie')
			<button class="btn sm primary" type="button" wire:click="setDrawerTab('Werkkopie')">{{ __('Werkkopie koppelen') }}</button>
		@endif
	</div>
@endif
