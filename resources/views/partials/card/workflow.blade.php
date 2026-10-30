<ol class="hsteps">
	@foreach ($steps as $case)
		<li class="{{ $case === $step ? 'now' : ($case->comesBefore($step) ? 'done' : '') }}">
			@unless ($loop->first)<span class="bar {{ $case->comesBefore($step) || $case === $step ? 'on' : '' }}"></span>@endunless
			<span class="n">{{ $case->number() }}</span>
			<span class="lbl">{{ $case->label() }}</span>
		</li>
	@endforeach
</ol>

@if ($step === \Src\Review\Enums\WorkflowStepEnum::BUILDING)
	<div class="field">
		<div class="fieldhead">
			<h3 class="eyebrow">{{ __('Bouwen · :done van :total gedaan', ['done' => collect($buildSteps)->where('isDone', true)->count(), 'total' => count($buildSteps)]) }}</h3>
			<span class="muted" style="font-size:12.5px">{{ __('op volgorde, want alles heet naar het ticket') }}</span>
		</div>
		<div class="linklist">
			@foreach ($buildSteps as $index => $buildStep)
				<div class="linkrow static">
					<span class="mark {{ $buildStep['isDone'] ? 'done' : ($buildStep['button'] ? 'now' : '') }}">{{ $buildStep['isDone'] ? '✓' : $index + 1 }}</span>
					<span class="grow">
						<b>{{ $buildStep['label'] }}</b>
						<small class="{{ $buildStep['key'] === 'worktree' || $buildStep['key'] === 'ticket' ? 'mono' : '' }}">{{ $buildStep['detail'] }}</small>
					</span>
					@if ($buildStep['button'])
						<button class="btn sm" type="button" wire:click="{{ $buildStep['action'] }}">{{ $buildStep['button'] }}</button>
					@elseif ($buildStep['isDone'])
						<span class="chip green">{{ __('staat') }}</span>
					@endif
				</div>
			@endforeach
		</div>
	</div>

	@if ($renameTo)
		<div class="note amber" style="flex-direction:column;align-items:stretch;gap:9px">
			<span style="display:flex;gap:9px;align-items:flex-start">
				<x-icon name="alert" small/>
				<span>{{ __('De branch :branch draagt het ticketnummer niet. Alles hangt aan dat nummer, dus hernoem hem voordat er gepusht wordt.', ['branch' => $card->branch]) }}</span>
			</span>
			<span class="linkform">
				<span class="row">
					<input class="input mono" type="text" wire:model="renameBranch" value="{{ $renameBranch }}" aria-label="{{ __('Nieuwe naam voor de branch') }}">
					<x-busy-button action="renameToTicket" class="btn" :busy="__('Hernoemen...')">{{ __('Hernoemen') }}</x-busy-button>
				</span>
				<span class="muted" style="font-size:12px">{{ __('Hernoemt de branch, de map van de werkkopie en de koppelingen op de taak.') }}</span>
			</span>
		</div>
	@endif
@endif

<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ $latestRound ? __('Ronde :number van :max', ['number' => $latestRound->number, 'max' => $maxRounds]) : __('Review') }}</h3>
		@if ($latestRound?->commitSha)<span class="muted mono" style="font-size:12.5px">{{ $latestRound->commitSha }}</span>@endif
	</div>

	@if ($latestRound)
		<div class="linklist">
			@foreach ($latestRound->results as $result)
				@if ($result->report)
					<details class="linkrow static" style="display:block;padding:0">
						<summary class="linkrow static" style="cursor:pointer;list-style:none;border-bottom:0">
							<span class="ldot {{ $result->verdict->tone() }}"></span>
							<span class="grow">
								<b class="mono">{{ $result->lensName }}</b>
								<small>{{ $result->summary() }}</small>
								@if ($result->progressLabel())<small class="muted">{{ $result->progressLabel() }}</small>@endif
							</span>
							@if ($result->verdict->canBeRetried())
								<button class="btn sm" type="button" wire:click.stop="retryLens({{ $result->lensId->id }})">{{ __('Opnieuw') }}</button>
							@endif
							<span class="chip {{ $result->verdict->chipTone() }}">{{ $result->verdict->label() }}</span>
						</summary>
						<div class="code light mono">{{ $result->report }}</div>
					</details>
				@else
					<div class="linkrow static">
						<span class="ldot {{ $result->verdict->tone() }}"></span>
						<span class="grow">
							<b class="mono">{{ $result->lensName }}</b>
							<small>{{ $result->summary() }}</small>
							@if ($result->progressLabel())<small class="muted">{{ $result->progressLabel() }}</small>@endif
						</span>
						@if ($result->verdict->canBeRetried())
							<button class="btn sm" type="button" wire:click="retryLens({{ $result->lensId->id }})">{{ __('Opnieuw') }}</button>
						@endif
						<span class="chip {{ $result->verdict->chipTone() }}">{{ $result->verdict->label() }}</span>
					</div>
				@endif
			@endforeach
		</div>
	@else
		<div class="prose empty-prose">{{ __('Nog geen ronde gedraaid. De lenzen gaan erover zodra je het werk oplevert.') }}</div>
	@endif
</div>

<div class="field">
	<h3 class="eyebrow">{{ __('Eerder') }}</h3>
	@if ($rounds->count() > 1)
		<div class="linklist">
			@foreach ($rounds->skip(1) as $round)
				<details class="linkrow static" style="display:block;padding:0">
					<summary class="linkrow static" style="cursor:pointer;list-style:none;border-bottom:0">
						<span class="lensdots">
							@foreach ($round->results as $result)<span class="ldot {{ $result->verdict->tone() }}"></span>@endforeach
						</span>
						<span class="grow">
							<b>{{ __('Ronde :number', ['number' => $round->number]) }}</b>
							<small>{{ $round->status->label() }}{{ $round->openFindings() ? ' · ' . trans_choice(':count punt open|:count punten open', $round->openFindings(), ['count' => $round->openFindings()]) : '' }}</small>
						</span>
						<span class="muted" style="font-size:12px">{{ $round->finishedAt?->diffForHumans() }}</span>
					</summary>
					<div class="code light mono">@foreach ($round->results as $result){{ $result->lensName }} · {{ $result->verdict->label() }}
@endforeach</div>
					@if (isset($agentTurns[$round->reviewRoundId->id]))
						<div class="code light mono">{{ $agentTurns[$round->reviewRoundId->id]->output ?? __('De agent gaf niets terug.') }}</div>
					@endif
				</details>
			@endforeach
		</div>
	@else
		<div class="prose empty-prose">{{ __('Dit is de eerste ronde. Een volgende leest dit rapport en vinkt per punt af.') }}</div>
	@endif
</div>

<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ __('Wat de agent leest') }}</h3>
		<span class="muted mono" style="font-size:12.5px">{{ $contextPath }}</span>
	</div>
	@if ($agentContext)
		<details class="menu-inline">
			<summary class="eyebrow">{{ __('De hele tekst') }}</summary>
			<div class="code light mono">{{ $agentContext }}</div>
		</details>
		<span class="muted" style="font-size:12.5px">{{ $worktree ? __('Dit bestand staat in de werkkopie en wordt bij elke ronde opnieuw geschreven. De teksten pas je aan op de Workflows-pagina.') : __('Zo komt hij eruit te zien. Hij wordt weggeschreven zodra de agent aan het werk gaat. De teksten pas je aan op de Workflows-pagina.') }}</span>
	@else
		<div class="prose empty-prose">{{ __('Nog niets om te tonen: deze taak heeft nog geen project.') }}</div>
	@endif
</div>

<div class="dfoot">
	@if ($latestRound?->isRunning())
		<x-busy-button action="stopRound" class="btn danger" :busy="__('Afbreken...')">{{ __('Ronde afbreken') }}</x-busy-button>
	@else
		<x-busy-button action="startRound" class="btn primary" :busy="__('Lenzen oproepen...')" :disabled="! $canDeliver || $rounds->count() >= $maxRounds">{{ __('Opleveren voor review') }}</x-busy-button>
	@endif
	<button class="btn" type="button" wire:click="setDrawerTab('Worktree')">{{ __('Naar de werkkopie') }}</button>
	<span class="hint">{{ $latestRound ? __('ronde :number van :max', ['number' => $latestRound->number, 'max' => $maxRounds]) : ($canDeliver ? $step->label() : __('nog niets om op te leveren')) }}</span>
</div>
