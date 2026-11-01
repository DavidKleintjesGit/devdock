<div class="dbody" @if ($latestRound?->isRunning()) wire:poll.3s="followProgress" @endif>
	@if ($isExpired)
		<div class="banner amber" style="padding:16px 24px">
			<div class="grow" style="display:flex;flex-direction:column;gap:2px;min-width:200px">
				<b style="font-size:15px">{{ __('Deze ronde is verlopen') }}</b>
				<span>{{ __('Het werk veranderde nadat ronde :number klaar was.', ['number' => $latestRound->number]) }}</span>
			</div>
		</div>
	@endif

	@forelse ($rounds as $round)
		<section class="dsec" style="padding-left:0;padding-right:0;gap:0" wire:key="round-{{ $round->reviewRoundId->id }}">
			<div class="headrow" style="padding:0 24px 10px">
				<h3 class="eyebrow" style="flex:none">{{ __('Ronde :number', ['number' => $round->number]) }}</h3>
				<span class="hint grow">{{ $round->startedAt?->calendar() }}@if ($round->finishedAt) · {{ $round->startedAt?->diffForHumans($round->finishedAt, true) }}@endif{{ $round->openFindings() ? ' · ' . trans_choice(':count bevinding|:count bevindingen', $round->openFindings(), ['count' => $round->openFindings()]) : '' }}</span>
				<span class="label {{ $round->status->tone() }}">{{ $round->status->label() }}</span>
			</div>
			@foreach ($round->results as $result)
				@if ($result->report && $result->verdict->hasFinding())
					<button class="row" type="button" style="min-height:44px;padding:0 24px;border-top:1px solid var(--line)" wire:click="openFinding({{ $round->reviewRoundId->id }}, {{ $result->lensId->id }})" wire:key="result-{{ $round->reviewRoundId->id }}-{{ $result->lensId->id }}">
						<span class="ldot {{ $result->verdict->dotTone() }}"></span>
						<b style="width:100px">{{ $result->lensName }}</b>
						<span class="grow hint" style="font-size:13px">{{ $result->summary() }}</span>
						@if ($result->verdict->canBeRetried())<span class="btn xs" wire:click.stop="retryLens({{ $result->lensId->id }})">{{ __('Opnieuw') }}</span>@endif
						<x-icon name="chev" small/>
					</button>
				@else
					<div class="row" style="min-height:44px;padding:0 24px;border-top:1px solid var(--line)" wire:key="result-{{ $round->reviewRoundId->id }}-{{ $result->lensId->id }}">
						<span class="ldot {{ $result->verdict->dotTone() }}"></span>
						<b style="width:100px">{{ $result->lensName }}</b>
						<span class="grow hint" style="font-size:13px">{{ $result->progressLabel() ?? $result->summary() }}</span>
						@if ($result->startedAt && $result->finishedAt)<span class="hint" style="font-size:12px">{{ $result->startedAt->diffForHumans($result->finishedAt, true, true) }}</span>@endif
					</div>
				@endif
			@endforeach
			@if (isset($agentTurns[$round->reviewRoundId->id]))
				<div class="hint" style="padding:10px 24px 0;border-top:1px solid var(--line)">{{ __('Aan de agent gegeven; hij werkte de punten bij voor de volgende ronde.') }}</div>
			@endif
		</section>
	@empty
		<section class="dsec">
			<div class="empty">
				<b>{{ __('Nog geen ronde gedraaid') }}</b>
				<span>{{ $canDeliver ? __('Er staat werk klaar. De lenzen kijken alleen naar wat deze branch veranderde.') : __('Zodra er werk in de werkkopie staat, kunnen de lenzen erover.') }}</span>
			</div>
		</section>
	@endforelse
</div>

<div class="dfoot">
	<span class="hint" style="display:flex;flex-direction:column">
		<span>{{ $latestRound ? __('Ronde :number van hoogstens :max, daarna :after', ['number' => $latestRound->number, 'max' => $maxRounds, 'after' => mb_strtolower($afterRounds->label())]) : __('Hoogstens :max rondes, daarna :after', ['max' => $maxRounds, 'after' => mb_strtolower($afterRounds->label())]) }}</span>
		<a href="{{ route('workflows', ['project' => $card->project]) }}" style="text-decoration:none">{{ __('Lenzen en drempel staan in de workflow') }}</a>
	</span>
	@if ($latestRound?->isRunning())
		<x-busy-button action="stopRound" class="btn" :busy="__('Afbreken...')">{{ __('Ronde afbreken') }}</x-busy-button>
	@else
		<x-busy-button action="startRound" class="btn primary" :busy="__('Lenzen oproepen...')" :disabled="! $canDeliver || $rounds->count() >= $maxRounds">{{ $latestRound ? __('Opnieuw beoordelen') : __('Naar de lenzen') }}</x-busy-button>
	@endif
</div>
