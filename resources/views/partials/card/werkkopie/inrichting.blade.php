<div class="dbody workspace setup-workspace">
	@if ($readiness)
		<p class="setup-intro">{{ __('Is deze kopie compleet om in te werken? DevDock kijkt na of alles erin staat wat de app nodig heeft. Pas als alles in orde is, gaan de agent en de lenzen aan de slag. Het meeste herstelt DevDock zelf.') }}</p>

		<section class="setup-progress">
			<span class="setup-count">
				<span><b>{{ __(':done van :total', ['done' => $readiness->readyCount(), 'total' => $readiness->totalCount()]) }}</b> <span class="hint">{{ __('in orde') }}</span></span>
				<span class="meter"><span style="width:{{ $readiness->readyPercentage() }}%"></span></span>
			</span>
			<span class="setup-line">
				@if ($repairState?->isRunning)
					<b>{{ __('Nu: :point', ['point' => $repairState->setupCheck?->label() ?? __('beginnen')]) }}@if ($repairState->step) · {{ $repairState->step }}@endif</b>
					<span class="hint">{{ __('Loopt in de achtergrond; je kunt intussen verder.') }}</span>
				@elseif ($repairState && $repairState->failures !== [])
					<b class="failed">{{ trans_choice(':count punt niet gelukt|:count punten niet gelukt', count($repairState->failures), ['count' => count($repairState->failures)]) }}@if ($setupWaitingCount > 0), {{ trans_choice(':count wacht erop|:count wachten erop', $setupWaitingCount, ['count' => $setupWaitingCount]) }}@endif</b>
					<span class="hint">{{ __('De andere punten zijn hersteld. Herstel het punt opnieuw, of los het in het project op; daarna gaan de wachtende punten vanzelf mee.') }}</span>
				@elseif ($readiness->isReady())
					<b>{{ __('Alles in orde') }}</b>
					<span class="hint">{{ __('nagekeken :when', ['when' => $readiness->checkedAt->diffForHumans()]) }}@if ($readiness->projectKind) · {{ $readiness->projectKind->label() }}@endif</span>
				@else
					<b>{{ trans_choice(':count punt open|:count punten open', $readiness->openCount(), ['count' => $readiness->openCount()]) }}</b>
					<span class="hint">{{ __('nagekeken :when', ['when' => $readiness->checkedAt->diffForHumans()]) }}@if ($readiness->projectKind) · {{ $readiness->projectKind->label() }}@endif</span>
				@endif
			</span>
			<x-busy-button action="checkSetupAgain" class="btn text xs" :busy="__('Nakijken...')">{{ __('Opnieuw nakijken') }}</x-busy-button>
			@if ($readiness->canRepairAny() && ! $repairState?->isRunning)
				<x-busy-button action="repairSetup" class="btn sm primary" :busy="__('Starten...')">{{ __('Alles herstellen') }}</x-busy-button>
			@endif
		</section>

		<div class="panel-grid setup-grid">
			@foreach ($setupGroups as $setupGroup)
				<x-panel :title="$setupGroup['group']->label()" :padded="false" class="setup-group {{ $setupGroup['state']->value }}">
					<x-slot:actions><span class="setup-group-state {{ $setupGroup['state']->value }}">{{ $setupGroup['state']->groupLabel() }}</span></x-slot:actions>
					<p class="setup-explanation">{{ $setupGroup['group']->explanation() }}</p>
					@foreach ($setupGroup['points'] as $setupPoint)
						<div class="setup-point {{ $setupPoint->state->value }}" wire:key="setup-{{ $setupPoint->setupCheck->setupCheck->value }}">
							<div class="setup-point-row">
								<span class="setup-mark">
									@switch ($setupPoint->state->value)
										@case ('done')<x-icon name="check" small/>@break
										@case ('failed')<x-icon name="alert" small/>@break
										@case ('waiting')<x-icon name="lock" small/>@break
										@case ('open')<x-icon name="alert" small/>@break
										@default<span class="setup-dot"></span>
									@endswitch
								</span>
								<span class="grow"><b>{{ $setupPoint->setupCheck->setupCheck->label() }}</b><span class="setup-text">{{ $setupPoint->text }}</span></span>
								@if ($setupPoint->setupCheck->repair && in_array($setupPoint->state->value, ['open', 'failed'], true))
									<button class="btn xs" type="button" wire:click="repairSetup('{{ $setupPoint->setupCheck->setupCheck->value }}')" @disabled(! $setupPoint->setupCheck->canRepair() || $repairState?->isRunning)>{{ $setupPoint->state->value === 'failed' ? __('Opnieuw') : __('Herstellen') }} · {{ $setupPoint->setupCheck->repair }}</button>
								@endif
							</div>
							@if ($setupPoint->state->value === 'failed')
								<div class="setup-failure">
									<span class="mono">{{ $setupPoint->reason }}</span>
									@if ($setupPoint->waitingPoints !== '')<span>{{ __('Wacht hierop: :points.', ['points' => $setupPoint->waitingPoints]) }}</span>@endif
									<a href="#repair-log">{{ __('Naar het log') }}</a>
								</div>
							@endif
						</div>
					@endforeach
				</x-panel>
			@endforeach
		</div>

		@if ($repairState)
			<details id="repair-log" class="panel setup-log" wire:ignore.self wire:key="repair-log-{{ $repairState->startedAt->getTimestamp() }}" @if ($repairState->isRunning || $repairState->failure) open @endif>
				<summary class="panel-head">
					<x-icon name="chev" small/>
					<h3 class="grow">{{ $repairState->isRunning ? __('Log, live') : __('Log van de laatste herstelactie') }}</h3>
					@unless ($repairState->isRunning)<span class="hint" @if ($repairState->failure) style="color:var(--red)" @endif>{{ $repairState->failure ? __('vastgelopen') : __('klaar') }} · {{ $repairState->finishedAt?->diffForHumans() }}</span>@endunless
					<button class="btn xs" type="button" x-data @click.prevent="navigator.clipboard.writeText($el.closest('details').querySelector('pre').textContent)">{{ __('Kopiëren') }}</button>
				</summary>
				<pre class="setup-log-text">{{ $repairState->logTail() }}</pre>
			</details>
		@endif
	@else
		<p class="hint">{{ __('Deze werkkopie is nog niet nagekeken.') }}</p>
	@endif
</div>
