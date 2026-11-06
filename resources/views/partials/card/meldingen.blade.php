<div class="notices">
	@if ($failure)
		<div class="notice failed" role="alert">
			<span class="notice-icon"><x-icon name="alert" small/></span>
			<span class="notice-text"><b>{{ $failure }}</b></span>
			<button class="icon-btn" type="button" wire:click="clearFailure" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button>
		</div>
	@endif

	@if ($card->hasUnreadQuestion && $tab !== 'Agent')
		<div class="notice question">
			<span class="notice-icon"><x-icon name="info" small/></span>
			<span class="notice-text"><b>{{ __('De agent stelt een vraag') }}</b><span>{{ __('Antwoord in de chat; daarna gaat hij verder.') }}</span></span>
			<button class="btn sm primary" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de vraag') }}</button>
		</div>
	@endif

	@include('partials.card.taak-balk')

	@if ($repairState?->isRunning)
		<div class="notice running" wire:poll.3s>
			<span class="notice-icon"><span class="sdot busy"></span></span>
			<span class="notice-text"><b>{{ __('DevDock herstelt de werkkopie') }}@if ($repairState->setupCheck) · {{ $repairState->setupCheck->label() }}@endif</b><span>{{ $repairState->step ?? __('Bezig met beginnen...') }}</span></span>
		</div>
	@elseif ($readiness && ! $readiness->isReady())
		<div class="notice blocks">
			<span class="notice-icon"><x-icon name="lock" small/></span>
			<span class="notice-text"><b>{{ __('De werkkopie is niet gereed · :open van :total punten open', ['open' => $readiness->openCount(), 'total' => $readiness->totalCount()]) }}</b><span>{{ __('De agent en de lenzen wachten tot hij compleet is. Lezen blijft open.') }}</span></span>
			@if ($readiness->canRepairAny())
				<x-busy-button action="repairSetup" :class="$tab === 'Werkkopie' ? 'btn sm primary' : 'btn sm'" :busy="__('Starten...')">{{ __('Alles herstellen') }}</x-busy-button>
			@endif
			@if ($tab !== 'Werkkopie')
				<button class="btn sm primary" type="button" wire:click="openSetupChecklist">{{ __('Checklist bekijken') }}</button>
			@endif
		</div>
	@elseif (! $worktree && ! $isSetup && $card->project !== null && ! $workingCopyTask?->isRunning)
		<div class="notice blocks">
			<span class="notice-icon"><x-icon name="lock" small/></span>
			<span class="notice-text"><b>{{ __('Deze taak heeft nog geen eigen werkkopie') }}</b><span>{{ __('De agent en de lenzen werken alleen in een eigen werkkopie. Lezen blijft open.') }}</span></span>
			@if ($tab !== 'Werkkopie')
				<button class="btn sm primary" type="button" wire:click="setDrawerTab('Werkkopie')">{{ __('Werkkopie koppelen') }}</button>
			@endif
		</div>
	@endif
</div>
