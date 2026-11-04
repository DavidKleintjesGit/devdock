<div class="dbody" @if ($latestRound?->isRunning() || $loopStatus === \Src\Review\Enums\LoopStatusEnum::AGENT) wire:poll.3s="followProgress" @endif>
	@if ($isExpired)
		<div class="banner amber" style="padding:16px 24px">
			<div class="grow" style="display:flex;flex-direction:column;gap:2px;min-width:200px">
				<b style="font-size:15px">{{ __('Deze ronde is verlopen') }}</b>
				<span>{{ __('Het werk veranderde nadat ronde :number klaar was.', ['number' => $latestRound->number]) }}</span>
			</div>
		</div>
	@endif

	<div class="review-loop">
		<div class="loop-status">
			<span class="sdot {{ $loopStatus->tone() }}"></span>
			<b>{{ $loopStatus->label($latestRound?->positionInSeries() ?? 0, $maxRounds, $latestRound?->findingsSummary() ?? '') }}</b>
			<span class="hint">{{ __('automatisch · hoogstens :max rondes', ['max' => $maxRounds]) }}</span>
		</div>

		@if ($loopStatus->needsYou())
			<div class="reportbar amber">
				@if ($loopStatus === \Src\Review\Enums\LoopStatusEnum::NEXT_ROUND)
					<p class="question">{{ __('De agent meldde dat hij klaar is, maar de lenzen gingen er niet vanzelf weer over, bijvoorbeeld omdat de werkkopie nog bezig was of er niets veranderde.') }}</p>
					<div class="reportbar-actions">
						<button class="btn sm" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de agent') }}</button>
						<x-busy-button action="startRound" class="btn sm primary" :busy="__('Lenzen oproepen...')">{{ __('Volgende ronde starten') }}</x-busy-button>
					</div>
				@else
				<p class="question">{{ $loopStatus === \Src\Review\Enums\LoopStatusEnum::AGENT_ASKS ? __('De agent stelde een vraag of liet bevindingen open. Geef antwoord bij de agent en start de loop daarna opnieuw.') : __('Kijk de rondes na, geef de agent aanwijzingen en start de loop opnieuw.') }}</p>
				<div class="reportbar-actions">
					<button class="btn sm" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de agent') }}</button>
					<x-busy-button action="restartLoop" class="btn sm primary" :busy="__('Lenzen oproepen...')">{{ trans_choice('Loop opnieuw starten · :count ronde|Loop opnieuw starten · :count rondes', $maxRounds, ['count' => $maxRounds]) }}</x-busy-button>
				</div>
				@endif
			</div>
		@endif

		@forelse ($roundRows as $roundRow)
			<details class="round" wire:ignore.self wire:key="round-{{ $roundRow['round']->reviewRoundId->id }}" @if ($loop->first) open @endif>
				<summary class="round-head">
					<x-icon name="chev" small/>
					<b>{{ __('Ronde :number', ['number' => $roundRow['round']->number]) }}</b>
					@if ($roundRow['round']->number === $roundRow['round']->seriesStart && $roundRow['round']->seriesStart > 1)<span class="label blue">{{ __('nieuwe loop') }}</span>@endif
					<span class="label {{ $roundRow['round']->status->tone() }}">{{ $roundRow['round']->findingsSummary() ?: $roundRow['round']->status->label() }}</span>
					@if ($roundRow['round']->commitSha)<span class="mono hint">{{ substr($roundRow['round']->commitSha, 0, 7) }}</span>@endif
					<span class="hint push-end">{{ $roundRow['round']->startedAt?->toDisplayZone()->calendar() }}</span>
				</summary>

				<section class="round-part">
					<h4 class="eyebrow">{{ __('Wat de lenzen vonden') }}</h4>
					@foreach ($roundRow['round']->results as $result)
						@if ($result->report)
							<button class="row" type="button" wire:click="openFinding({{ $roundRow['round']->reviewRoundId->id }}, {{ $result->lensId->id }})" wire:key="result-{{ $roundRow['round']->reviewRoundId->id }}-{{ $result->lensId->id }}">
								<span class="ldot {{ $result->verdict->dotTone() }}"></span>
								<b class="lens-name">{{ $result->lensName }}</b>
								<span class="grow hint">{{ $result->summary() }}</span>
								@if ($result->verdict->canBeRetried())<span class="btn xs" wire:click.stop="retryLens({{ $result->lensId->id }})">{{ __('Opnieuw') }}</span>@endif
								<x-icon name="chev" small/>
							</button>
						@else
							<div class="row" wire:key="result-{{ $roundRow['round']->reviewRoundId->id }}-{{ $result->lensId->id }}">
								<span class="ldot {{ $result->verdict->dotTone() }}"></span>
								<b class="lens-name">{{ $result->lensName }}</b>
								<span class="grow hint">{{ $result->progressLabel() ?? $result->summary() }}</span>
								@if ($result->startedAt && $result->finishedAt)<span class="hint">{{ $result->startedAt->diffForHumans($result->finishedAt, true, true) }}</span>@endif
							</div>
						@endif
					@endforeach
				</section>

				@if ($roundRow['turn'])
					<section class="round-part">
						<h4 class="eyebrow">{{ __('Wat de agent ermee deed') }}</h4>
						<span class="hint">
							{{ $card->agent?->name ?? __('agent') }} ·
							@if ($roundRow['turn']->run->isRunning())
								{{ __('bezig sinds :time', ['time' => $roundRow['turn']->run->startedAt?->toDisplayZone()->format('H:i')]) }}
							@else
								{{ __(':count s', ['count' => $roundRow['turn']->run->seconds()]) }}{{ $roundRow['turn']->run->costLabel() ? ' · ' . $roundRow['turn']->run->costLabel() : '' }}
							@endif
						</span>
						@if ($roundRow['turn']->report)
							<p class="turn-summary">{{ $roundRow['turn']->report->summary }}</p>
							<ul class="turn-points">
								@foreach ($roundRow['turn']->report->done as $donePoint)
									<li><x-icon name="check" small/>{{ $donePoint }}</li>
								@endforeach
								@foreach ($roundRow['turn']->report->open as $openPoint)
									<li class="open"><x-icon name="alert" small/>{{ __('Open') }}: {{ $openPoint }}</li>
								@endforeach
							</ul>
						@elseif ($roundRow['turn']->run->hasFailed())
							<p class="turn-summary" style="color:var(--red)">{{ $roundRow['turn']->run->message }}</p>
						@endif
						<button class="btn xs" type="button" style="align-self:flex-start" wire:click="setDrawerTab('Agent')">{{ __('Hele gesprek') }}</button>
					</section>

					@if ($roundRow['turn']->changes !== [])
						<section class="round-part">
							<h4 class="eyebrow">
								{{ trans_choice('Wat er veranderde · :count bestand|Wat er veranderde · :count bestanden', count($roundRow['turn']->changes), ['count' => count($roundRow['turn']->changes)]) }}
								· +{{ $roundRow['turn']->addedLines() }} −{{ $roundRow['turn']->removedLines() }}
							</h4>
							@foreach ($roundRow['turn']->changes as $change)
								<button class="filerow" type="button" wire:click="openTurnDiff('{{ $change->path }}', {{ $roundRow['turn']->run->promptRunId->id }})" wire:key="round-change-{{ $roundRow['round']->reviewRoundId->id }}-{{ $loop->index }}">
									<span class="name"><b>{{ $change->name() }}</b><small>{{ $change->folder() }}@if ($change->isNew()) · {{ __('nieuw') }}@endif</small></span>
									<span class="delta">@if ($change->added)<span class="plus">+{{ $change->added }}</span>@endif @if ($change->removed)<span class="minus">−{{ $change->removed }}</span>@endif</span>
									<x-icon name="chev" small/>
								</button>
							@endforeach
						</section>
					@endif
				@endif
			</details>
		@empty
			<div class="empty">
				<b>{{ __('Nog geen ronde gedraaid') }}</b>
				<span>{{ $canDeliver ? __('Er staat werk klaar. De lenzen kijken alleen naar wat deze branch veranderde.') : __('Zodra er werk in de werkkopie staat, kunnen de lenzen erover.') }}</span>
			</div>
		@endforelse
	</div>
</div>

<div class="dfoot">
	<span class="hint" style="display:flex;flex-direction:column">
		<span>{{ $latestRound ? __('Ronde :number van hoogstens :max, daarna :after', ['number' => $latestRound->positionInSeries(), 'max' => $maxRounds, 'after' => mb_strtolower($afterRounds->label())]) : __('Hoogstens :max rondes, daarna :after', ['max' => $maxRounds, 'after' => mb_strtolower($afterRounds->label())]) }}</span>
		<a href="{{ route('workflows', ['project' => $card->project]) }}" style="text-decoration:none">{{ __('Lenzen en drempel staan in de workflow') }}</a>
	</span>
	@if ($latestRound?->isRunning())
		<x-busy-button action="stopRound" class="btn" :busy="__('Afbreken...')">{{ __('Ronde afbreken') }}</x-busy-button>
	@elseif (! $loopStatus->needsYou())
		<x-busy-button action="startRound" class="btn primary" :busy="__('Lenzen oproepen...')" :disabled="! $canDeliver">{{ $latestRound ? __('Opnieuw beoordelen') : __('Naar de lenzen') }}</x-busy-button>
	@endif
</div>
