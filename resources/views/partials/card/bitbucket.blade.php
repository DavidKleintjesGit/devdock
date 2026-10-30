@if ($card->pullRequests->isNotEmpty())
	<div class="field">
		<div class="fieldhead">
			<h3 class="eyebrow">{{ __('Pull requests') }}</h3>
			<span class="muted" style="font-size:12.5px">{{ __('één actief') }}</span>
		</div>
		<div class="linklist">
			@foreach ($card->pullRequests as $pullRequest)
				<div class="linkrow static">
					<span class="dot {{ $pullRequest->repository . '#' . $pullRequest->number === $card->activePullRequest ? 'green' : '' }}"></span>
					<span class="grow">
						<b>#{{ $pullRequest->number }} · {{ $pullRequest->title }}</b>
						<small>{{ $pullRequest->state->label() }} · {{ __(':approved van :total goedgekeurd', ['approved' => $pullRequest->approvals, 'total' => $pullRequest->reviewers]) }}@if ($pullRequest->changesRequestedBy) · {{ $pullRequest->changesRequestedBy }}@endif</small>
					</span>
					@if ($pullRequest->repository . '#' . $pullRequest->number === $card->activePullRequest)
						<span class="chip green">{{ __('actief') }}</span>
					@else
						<button class="btn sm" type="button" wire:click="activate('pull_request', '{{ $pullRequest->repository }}#{{ $pullRequest->number }}')">{{ __('Op actief zetten') }}</button>
					@endif
					<a class="btn icon ghost" href="{{ $pullRequest->url }}" target="_blank" rel="noopener" aria-label="{{ __('Openen in Bitbucket') }}"><x-icon name="chev" small/></a>
				</div>
			@endforeach
		</div>
	</div>
@else
	<div class="box">
		<div class="empty">
			<span class="bubble"><x-icon name="pr"/></span>
			<b>{{ __('Nog geen pull request') }}</b>
			<span>{{ __('Zodra er een openstaat op deze branch verschijnt hij hier.') }}</span>
		</div>
	</div>
@endif

<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ __('Open opmerkingen') }}</h3>
		<span class="muted" style="font-size:12.5px">{{ $hasReviewPrompt ? __('de prompt van de reviewer laten we buiten de lijst') : trans_choice(':count open|:count open', $reviewComments->count(), ['count' => $reviewComments->count()]) }}</span>
	</div>
	@if ($reviewComments->isNotEmpty())
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
	@else
		<div class="prose empty-prose">{{ $card->pullRequests->isEmpty() ? __('Er is nog geen pull request om opmerkingen op te krijgen.') : __('Niets staat open. Zodra een reviewer iets achterlaat staat het hier, met bestand en regel erbij.') }}</div>
	@endif
</div>

@if ($pullRequestRows)
	<div class="linkform">
		<div class="row">
			<input class="input" type="text" wire:model="linkPullRequest" value="{{ $linkPullRequest }}" placeholder="{{ __('Repository#nummer, bijvoorbeeld portal#2606') }}">
			<button class="btn" type="button" wire:click="link('pull_request')">{{ __('PR vastzetten') }}</button>
		</div>
	</div>
@endif

<div class="dfoot">
	<button class="btn primary" type="button" wire:click="fillPromptFromReview" wire:target="fillPromptFromReview" wire:loading.attr="disabled" @disabled($reviewComments->isEmpty())><x-icon name="chat" small/>{{ __('Prompt uit deze review') }}</button>
	@if ($activePullRequestUrl)
		<a class="btn" href="{{ $activePullRequestUrl }}" target="_blank" rel="noopener"><x-icon name="pr" small/>{{ __('Openen in Bitbucket') }}</a>
	@endif
	<span class="hint">{{ $card->activePullRequest ?? __('Zonder pull request') }}</span>
</div>
