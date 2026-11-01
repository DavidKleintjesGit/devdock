<div class="dbody">
	<div class="banner {{ $nextStep['tone'] }}">
		<div class="grow" style="display:flex;flex-direction:column;gap:3px;min-width:200px">
			<b>{{ $nextStep['title'] }}</b>
			<span>{{ $nextStep['text'] }}</span>
		</div>
	</div>

	<section class="dsec">
		<h3 class="eyebrow">{{ $card->ticketKey ? __('Het ticket') : __('De notitie') }}</h3>
		@if ($description)
			<p style="white-space:pre-line">{{ $description }}</p>
		@else
			<p class="muted">{{ __('Nog geen beschrijving. Een ticket of een notitie op deze taak vult dit.') }}</p>
		@endif
		@if ($card->ticketUrl)<a href="{{ $card->ticketUrl }}" target="_blank" rel="noopener" style="font-size:13px;text-decoration:none">{{ __('Openen in Jira') }}</a>@endif
	</section>

	@if ($reviewComments->isNotEmpty() || $changesRequestedBy)
		<section class="dsec">
			<div class="headrow">
				<h3 class="eyebrow">{{ __('Uit de review op Bitbucket') }}</h3>
				<span class="label amber">{{ trans_choice(':count open|:count open', $reviewComments->count(), ['count' => $reviewComments->count()]) }}</span>
			</div>
			<div class="list">
				@if ($changesRequestedBy)
					<a class="row tall" href="{{ $activePullRequest?->url }}" target="_blank" rel="noopener" style="background:var(--amber-wash);flex-direction:column;align-items:flex-start;gap:3px">
						<span style="display:flex;gap:8px;align-items:center;font-size:12.5px"><b>{{ $changesRequestedBy }}</b><span class="label amber">{{ __('Requested changes') }}</span></span>
						<span class="muted">{{ __('Vraagt om wijzigingen op :pr.', ['pr' => $card->activePullRequest]) }}</span>
					</a>
				@endif
				@foreach ($reviewComments as $comment)
					<a class="row tall" href="{{ $comment->url }}" target="_blank" rel="noopener" style="flex-direction:column;align-items:flex-start;gap:3px">
						<span style="display:flex;gap:8px;font-size:12.5px;color:var(--muted)"><b style="color:var(--fg)">{{ $comment->author }}</b><span class="mono" style="font-size:12px">{{ $comment->path ? $comment->path . ($comment->line ? ':' . $comment->line : '') : __('op de pull request zelf') }}</span></span>
						<span style="color:var(--fg-2)">{{ $comment->title() }}</span>
					</a>
				@endforeach
			</div>
		</section>
	@endif

	<section class="dsec">
		<h3 class="eyebrow">{{ __('Deze taak') }}</h3>
		<div class="list">
			<div class="row"><span class="key">{{ __('Project') }}</span><span class="val mono">{{ $card->project ?? __('nog geen') }}</span><button class="btn xs" type="button" wire:click="setDrawerTab('Koppelingen')">{{ __('Wijzigen') }}</button></div>
			<div class="row"><span class="key">{{ __('Branch') }}</span><span class="val mono">{{ $card->branch ?? __('nog geen') }}</span><button class="btn xs" type="button" wire:click="setDrawerTab('Koppelingen')">{{ __('Wijzigen') }}</button></div>
			<div class="row"><span class="key">{{ __('Werkkopie') }}</span><span class="val">{{ $worktree ? ($worktree->waitingLabel() ?? __('schoon')) : __('nog geen eigen map') }}</span><button class="btn xs" type="button" wire:click="setDrawerTab('Werkkopie')">{{ __('Wijzigen') }}</button></div>
			<div class="row"><span class="key">{{ __('Agent') }}</span><span class="val mono">{{ $card->agent?->name ?? __('nog geen') }}</span><button class="btn xs" type="button" wire:click="setDrawerTab('Agent')">{{ __('Wijzigen') }}</button></div>
		</div>
	</section>
</div>

<div class="dfoot">
	<span class="hint">{{ $nextStep['hint'] }}</span>
	@if ($nextStep['tab'])
		<button class="btn primary" type="button" wire:click="setDrawerTab('{{ $nextStep['tab'] }}')">{{ $nextStep['button'] }}<x-icon name="arrow" small/></button>
	@elseif ($nextStep['url'])
		<a class="btn primary" href="{{ $nextStep['url'] }}" target="_blank" rel="noopener">{{ __('Openen in Bitbucket') }}<x-icon name="external" small/></a>
	@endif
</div>
