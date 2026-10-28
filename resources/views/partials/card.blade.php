<article class="kcard{{ $openCard === $card->id ? ' open' : '' }}" wire:key="card-{{ $card->id }}" wire:click="open('{{ $card->id }}')" role="button" tabindex="0">
	<span class="row">
		<span class="rowlink ticket">{{ $card->ticketKey ?? __('Zonder ticket') }}</span>
		@if ($card->ticketStatus)<span class="chip amber">{{ $card->ticketStatus }}</span>@endif
		<span class="date">{{ $card->dateLabel() }}</span>
	</span>
	<h3>{{ $card->title }}</h3>
	<span class="meta">
		@foreach ($card->pullRequests as $pullRequest)
			<span><x-icon name="pr" small/>#{{ $pullRequest->number }}{{ $pullRequest->isMerged() ? ' ' . __('gemerged') : '' }}</span>
		@endforeach
		@if ($card->commentCount())<span><x-icon name="chat" small/>{{ __(':count opmerkingen', ['count' => $card->commentCount()]) }}</span>@endif
		@if ($card->changedFiles)<span><x-icon name="file" small/>{{ __(':count gewijzigd', ['count' => $card->changedFiles]) }}</span>@endif
		@if ($card->sessions->count() > 1)<span><x-icon name="chat" small/>{{ __(':count sessies', ['count' => $card->sessions->count()]) }}</span>@endif
		@if ($card->promptCount())<span>{{ __(':count prompts', ['count' => $card->promptCount()]) }}</span>@endif
		<span><x-icon name="folder" small/>{{ $card->project ?? __('Zonder project') }}</span>
		@if ($card->branch && config('devdock.board.show_branch'))<span class="mono"><x-icon name="branch" small/>{{ $card->branch }}</span>@endif
	</span>
	@if ($card->note)
		<span class="note {{ $card->tone }}"><x-icon :name="$card->tone === 'green' ? 'check' : 'alert'" small/><span>{{ $card->note }}</span></span>
	@endif
</article>
