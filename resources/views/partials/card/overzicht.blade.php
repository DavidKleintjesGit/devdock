<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ __('Beschrijving') }}</h3>
		<span class="muted" style="font-size:12.5px">{{ __('gaat mee als context naar de agent') }}</span>
	</div>
	@if ($description)
		<div class="prose">{{ $description }}</div>
	@else
		<div class="prose empty-prose">{{ __('Nog geen beschrijving. Een ticket of een notitie op deze taak vult dit.') }}</div>
	@endif
</div>

<dl class="facts">
	<div><dt>{{ __('Wacht op') }}</dt><dd>{{ $card->column->waitsOn() }}</dd></div>
	<div><dt>{{ __('Agent') }}</dt><dd>{!! $card->agent ? e($card->agent->name) . '<small class="mono">' . e($card->agent->shortKey()) . '</small>' : e(__('Nog geen agent')) !!}</dd></div>
	<div><dt>{{ __('Lokaal') }}</dt><dd>{{ $card->changedFiles ? __(':count gewijzigd', ['count' => $card->changedFiles]) : __('Niets gewijzigd') }}</dd></div>
	<div><dt>{{ __('Werkkopie') }}</dt><dd>{{ $worktree ? ($worktree->waitingLabel() ?? __('Schoon')) : __('Nog geen eigen map') }}@if ($worktree)<small class="mono">{{ $worktree->path }}</small>@endif</dd></div>
</dl>

@if ($card->note)
	<span class="note {{ $card->tone }}"><x-icon :name="$card->tone === 'green' ? 'check' : 'alert'" small/><span>{{ $card->note }}</span></span>
@endif

<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ __('Actieve koppelingen') }}</h3>
		<span class="muted" style="font-size:12.5px">{{ __('wisselen doe je per tab') }}</span>
	</div>
	@if ($links)
		<div class="linklist">
			@foreach ($links as $link)
				<div class="linkrow static">
					<x-icon :name="$link['kind']->icon()" small/>
					<span class="grow">
						<b class="mono">{{ $link['value'] }}</b>
						<small>{{ $link['kind']->label() }}</small>
					</span>
				</div>
			@endforeach
		</div>
	@else
		<div class="prose empty-prose">{{ __('Nog niets gekoppeld. Zet een ticket, project, branch of pull request vast op de tab die erbij hoort.') }}</div>
	@endif
</div>

<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ __('Laatste antwoord') }}</h3>
		@if ($lastReport)<span class="chip {{ $lastReport->status->tone() }}">{{ $lastReport->status->label() }}</span>@endif
	</div>
	@if ($lastReport)
		<p class="lede">{{ $lastReport->summary }}</p>
	@else
		<div class="prose empty-prose">{{ __('De agent heeft nog niets teruggestuurd. Wat hij antwoordt komt hier als samenvatting te staan.') }}</div>
	@endif
</div>

<div class="dfoot">
	<button class="btn primary" type="button" wire:click="setDrawerTab('Agent')">
		<x-icon name="chat" small/>{{ $lastReport ? $lastReport->status->waitsOn() : __('Naar de agent') }}
	</button>
	<button class="btn {{ $confirmingDeleteTask ? 'danger solid' : 'danger' }}" type="button" wire:click="deleteTask">
		{{ $confirmingDeleteTask ? __('Zeker weten? Verwijderen') : __('Taak verwijderen') }}
	</button>
	<span class="hint">{{ $card->agent?->name ?? __('Nog geen agent') }}</span>
</div>
