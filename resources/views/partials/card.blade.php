<button type="button" @class(['kcard', 'done' => $card->isDone(), 'todo' => $card->isTodo()]) :class="{ open: $store.board.openCard === @js($card->id) }" wire:key="card-{{ $card->id }}" @click="$store.board.pending = { card: @js($card->id), key: @js($card->ticketKey ?? $card->project ?? __('to-do')), title: @js($card->title) }" wire:click="$dispatchTo('drawer', 'open-card', { card: @js($card->id) })">
	@if ($card->hasUnread())
		<span @class(['kunread', 'question' => $card->hasUnreadQuestion]) title="{{ $card->hasUnreadQuestion ? __('De agent stelt een vraag') : trans_choice(':count nieuwe melding|:count nieuwe meldingen', $card->unreadCount, ['count' => $card->unreadCount]) }}">{{ $card->unreadCount }}</span>
	@endif
	<span class="kline">
		@if ($card->isHighPriority())<svg class="ic s prio" width="11" height="11" role="img" aria-label="{{ __('Hoge prioriteit') }}"><use href="#i-prio"/></svg>@endif
		<span class="kkey">{{ $card->ticketKey ?? ($card->isTodo() ? __('to-do') : __('zonder ticket')) }}</span>
		@if ($card->project)<span>·</span><span class="kproject">{{ $card->project }}</span>@else<span class="kproject"></span>@endif
		@if ($card->behind > 0)<span class="kbehind" title="{{ trans_choice(':count commit achter op :main|:count commits achter op :main', $card->behind, ['count' => $card->behind, 'main' => $card->mainBranch]) }}">↓{{ $card->behind }}</span>@endif
		<span>{{ $card->ageLabel() }}</span>
	</span>
	<span class="ktitle">{{ $card->title }}</span>
	<span class="kstatus">
		@if ($card->lensStates !== [])
			<span class="ldots" aria-hidden="true">@foreach ($card->lensStates as $state)<span class="ldot {{ $state }}"></span>@endforeach</span>
		@else
			<span class="sdot {{ $card->status->value }}"></span>
		@endif
		<span @class(['txt', 'yours' => $card->isYourTurn(), $card->status->value])>{{ $card->statusText }}</span>
		@if ($card->copyPlace?->isWorthShowing())
			<svg class="ic s kplace" width="13" height="13" role="img" aria-label="{{ $card->copyPlace->label() }}"><title>{{ $card->copyPlace->label() }}</title><use href="#{{ $card->copyPlace->icon() }}"/></svg>
		@endif
		@if ($card->isStale)
			<span class="wicon pause" title="{{ __('Stilgevallen') }}"><svg class="ic s" width="12" height="12" role="img" aria-label="{{ __('Stilgevallen') }}"><use href="#i-pause"/></svg></span>
		@endif
	</span>
</button>
