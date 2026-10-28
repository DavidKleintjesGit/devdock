<aside class="drawer" role="dialog" aria-labelledby="drawer-title">
	<div class="grab" aria-hidden="true"></div>
	<div class="dbar">
		@if ($previous)
			<button class="back" type="button" wire:click="back"><x-icon name="back" small/><span>{{ $previous }}</span></button>
		@else
			<span class="kind">{{ __('Taak') }}</span>
		@endif
		<button class="btn icon ghost close" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<div class="row">
			<span class="chip {{ $card->column->tone() }}">{{ $card->column->label() }}</span>
			@if ($card->ticketStatus)<span class="chip amber">{{ $card->ticketStatus }}</span>@endif
			<span class="right">{{ $card->dateLabel() }}</span>
		</div>
		<h2 id="drawer-title">{{ $card->title }}</h2>
		<p class="sub">{{ $card->ticketKey ?? __('Zonder ticket') }} · {{ $card->project ?? __('Zonder project') }}</p>
	</div>

	<div class="tabs" role="tablist">
		@foreach (['Overzicht', 'Prompt'] as $name)
			<button type="button" role="tab" aria-selected="{{ $drawerTab === $name ? 'true' : 'false' }}" wire:click="setDrawerTab('{{ $name }}')">{{ __($name) }}</button>
		@endforeach
	</div>

	<div class="dbody">
	@if ($drawerTab === 'Prompt')
		@include('partials.card-prompt')
	@else
		<dl class="facts">
			<div><dt>{{ __('Wacht op') }}</dt><dd>{{ $card->column->waitsOn() }}</dd></div>
			<div><dt>{{ __('Branch') }}</dt><dd>{!! $card->branch ? '<span class="mono">' . e($card->branch) . '</span>' : e(__('Geen branch')) !!}</dd></div>
			<div><dt>{{ __('Sessies') }}</dt><dd>{{ $card->sessions->count() }}<small>{{ __(':count prompts', ['count' => $card->promptCount()]) }}</small></dd></div>
			@if ($card->changedFiles)
				<div><dt>{{ __('Lokaal') }}</dt><dd>{{ __(':count gewijzigd', ['count' => $card->changedFiles]) }}</dd></div>
			@endif
		</dl>

		@if ($card->note)
			<span class="note {{ $card->tone }}"><x-icon :name="$card->tone === 'green' ? 'check' : 'alert'" small/><span>{{ $card->note }}</span></span>
		@endif

		@if ($reviewComments->isNotEmpty())
			<div class="field">
				<h3 class="eyebrow">{{ __('Review · :count open', ['count' => $reviewComments->count()]) }}</h3>
				<div class="linklist">
					@foreach ($reviewComments as $comment)
						<a class="linkrow" href="{{ $comment->url }}" target="_blank" rel="noopener">
							<span class="grow">
								<b>{{ $comment->isReply() ? __('Reactie: :title', ['title' => $comment->title()]) : $comment->title() }}</b>
								<small class="mono">{{ $comment->location() ?? __('Op de pull request zelf') }}</small>
							</span>
							@if ($comment->weight())<span class="chip amber">{{ $comment->weight() }}</span>@endif
						</a>
					@endforeach
				</div>
				@if ($hasReviewPrompt)
					<span class="muted" style="font-size:12.5px">{{ __('De reviewer schreef er ook een eigen prompt bij; die laten we buiten de lijst.') }}</span>
				@endif
			</div>
		@endif

		@if ($card->ticketUrl || $card->pullRequests->isNotEmpty())
		<div class="field">
			<h3 class="eyebrow">{{ __('Openen') }}</h3>
			<div style="display:flex;gap:8px;flex-wrap:wrap">
				@if ($card->ticketUrl)
					<a class="btn primary" href="{{ $card->ticketUrl }}" target="_blank" rel="noopener"><x-icon name="file" small/>{{ $card->ticketKey }} {{ __('in Jira') }}</a>
				@endif
				@foreach ($card->pullRequests as $pullRequest)
					<a class="btn" href="{{ $pullRequest->url }}" target="_blank" rel="noopener"><x-icon name="pr" small/>#{{ $pullRequest->number }} {{ __('in Bitbucket') }}</a>
				@endforeach
			</div>
		</div>
		@endif

		@if ($card->pullRequests->isNotEmpty())
			<div class="field">
				<h3 class="eyebrow">{{ __('Pull requests') }}</h3>
				<div class="linklist">
					@foreach ($card->pullRequests as $pullRequest)
						<a class="linkrow" href="{{ $pullRequest->url }}" target="_blank" rel="noopener">
							<span class="grow">
								<b>#{{ $pullRequest->number }} · {{ $pullRequest->title }}</b>
								<small>{{ $pullRequest->state->label() }} · {{ __(':approved van :total goedgekeurd', ['approved' => $pullRequest->approvals, 'total' => $pullRequest->reviewers]) }}@if ($pullRequest->changesRequestedBy) · {{ $pullRequest->changesRequestedBy }}@endif</small>
							</span>
							<x-icon name="chev" small/>
						</a>
					@endforeach
				</div>
			</div>
		@endif

		<div class="field">
			<h3 class="eyebrow">{{ __('Sessies') }}</h3>
			@if ($card->sessions->isNotEmpty())
				<div class="linklist">
					@foreach ($card->sessions as $session)
						<button class="linkrow" type="button" wire:click="openDrawer('session', '{{ $session->uuid }}')">
							<span class="dot {{ $session->status->tone() }}"></span>
							<span class="grow">
								<b>{{ $session->name() }}</b>
								<small>{{ __(':count prompts', ['count' => $session->promptCount]) }} · {{ $session->sizeLabel() }} · {{ $session->lastActiveLabel() }}</small>
							</span>
							<x-icon name="chev" small/>
						</button>
					@endforeach
				</div>
			@else
				<div class="box">
					<div class="empty">
						<span class="bubble"><x-icon name="chat"/></span>
						<b>{{ __('Nog geen sessie') }}</b>
						<span>{{ __('Er is nog niet met Claude aan deze taak gewerkt.') }}</span>
					</div>
				</div>
			@endif
		</div>

		@if ($card->branch && $card->project)
			<div class="resume">
				<b>{{ __('Verder in Claude Code') }}</b>
				<div class="code mono">cd {{ config('devdock.paths.sites') }}/{{ $card->project }}
git switch {{ $card->branch }}</div>
			</div>
		@endif
	@endif
	</div>
</aside>
