<div class="notices">
	@if ($failure)
		<x-notice property="failure" tone="failed">{{ $failure }}</x-notice>
	@elseif ($setupRefusal)
		<div class="notice failed" role="alert">
			<span class="notice-icon"><x-icon name="alert" small/></span>
			<span class="notice-text"><b>{{ __('Klaarzetten lukte niet') }}</b><span>{{ __('Bij stap 2 staat wat er misging en wat je kunt doen.') }}</span></span>
		</div>
	@endif

	@if ($committed)
		<x-notice property="committed">{{ $committed }}</x-notice>
	@endif

	@if ($card->hasUnreadQuestion && $tab !== 'Agent' && ! ($roundAssessment ?? null))
		<div class="notice question">
			<span class="notice-icon"><x-icon name="info" small/></span>
			<span class="notice-text"><b>{{ __('De agent stelt een vraag') }}</b><span>{{ __('Antwoord in de chat; daarna gaat hij verder.') }}</span></span>
			<button class="btn sm primary" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de vraag') }}</button>
		</div>
	@endif

	@include('partials.card.taak-balk')

	@if ($tab === 'Review' && $subtab?->value !== 'lenzen')
		@if ($loopStatus?->needsYou() && ! ($roundAssessment ?? null))
			<div class="notice blocks">
				<span class="notice-icon"><x-icon name="arrow" small/></span>
				@if ($loopStatus === \Src\Review\Enums\LoopStatusEnum::NEXT_ROUND)
					<span class="notice-text"><b>{{ __('De lenzen gingen er niet vanzelf weer over') }}</b><span>{{ __('De agent meldde dat hij klaar is, maar de werkkopie was nog bezig of er veranderde niets.') }}</span></span>
					<button class="btn sm" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de agent') }}</button>
					<x-busy-button action="startRound" :pending="(bool) $workingCopyTask?->isPending(\Src\Worktree\Enums\WorkingCopyTaskEnum::ROUND)" class="btn sm primary" :busy="__('Lenzen oproepen...')">{{ __('Volgende ronde starten') }}</x-busy-button>
				@else
					<span class="notice-text"><b>{{ $loopStatus === \Src\Review\Enums\LoopStatusEnum::AGENT_ASKS ? __('De agent stelde een vraag of liet bevindingen open') : __('De loop is klaar en wacht op jou') }}</b><span>{{ $loopStatus === \Src\Review\Enums\LoopStatusEnum::AGENT_ASKS ? __('Geef antwoord bij de agent en start de loop daarna opnieuw.') : __('Kijk de rondes na, geef de agent aanwijzingen en start de loop opnieuw.') }}</span></span>
					<button class="btn sm" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de agent') }}</button>
					<x-busy-button action="restartLoop" :pending="(bool) $workingCopyTask?->isPending(\Src\Worktree\Enums\WorkingCopyTaskEnum::ROUND)" class="btn sm primary" :busy="__('Lenzen oproepen...')">{{ trans_choice('Loop opnieuw starten · :count ronde|Loop opnieuw starten · :count rondes', $maxRounds, ['count' => $maxRounds]) }}</x-busy-button>
				@endif
			</div>
		@endif

		@if ($isExpired)
			<div class="notice blocks">
				<span class="notice-icon"><x-icon name="alert" small/></span>
				<span class="notice-text"><b>{{ __('Deze ronde is verlopen') }}</b><span>{{ __('Het werk veranderde nadat ronde :number klaar was.', ['number' => $latestRound->number]) }}</span></span>
			</div>
		@endif
	@endif

	@if ($tab === 'Overzicht')
		<div class="notice {{ $nextStep['tone'] }}">
			<span class="notice-icon"><x-icon name="arrow" small/></span>
			<span class="notice-text"><b>{{ $nextStep['title'] }}</b><span>{{ $nextStep['text'] }}</span></span>
			@if ($nextStep['tab'])
				<button class="btn sm primary" type="button" wire:click="setDrawerTab('{{ $nextStep['tab'] }}')" title="{{ $nextStep['hint'] }}">{{ $nextStep['button'] }}<x-icon name="arrow" small/></button>
			@elseif ($nextStep['url'])
				<a class="btn sm primary" href="{{ $nextStep['url'] }}" target="_blank" rel="noopener" title="{{ $nextStep['hint'] }}">{{ __('Openen in Bitbucket') }}<x-icon name="external" small/></a>
			@endif
		</div>
	@endif

	@if ($repairState?->isRunning)
		<div class="notice running" wire:poll.3s>
			<span class="notice-icon"><span class="sdot busy"></span></span>
			<span class="notice-text"><b>{{ __('DevDock herstelt de werkkopie') }}@if ($repairState->setupCheck) · {{ $repairState->setupCheck->label() }}@endif</b><span>{{ $repairState->step ?? __('Bezig met beginnen...') }}</span></span>
		</div>
	@elseif ($repairState && $repairState->failures !== [] && $readiness && ! $readiness->isReady())
		<div class="notice failed">
			<span class="notice-icon"><x-icon name="alert" small/></span>
			<span class="notice-text"><b>{{ trans_choice('Herstel niet helemaal gelukt · :count punt|Herstel niet helemaal gelukt · :count punten', count($repairState->failures), ['count' => count($repairState->failures)]) }}</b><span>{{ __('De rest is wel hersteld. De agent en de lenzen wachten tot de werkkopie compleet is.') }}</span></span>
			@if (! ($tab === 'Werkkopie' && $subtab?->value === 'inrichting'))
				<button class="btn sm primary" type="button" wire:click="openSetupChecklist">{{ __('Naar het punt') }}</button>
			@endif
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
