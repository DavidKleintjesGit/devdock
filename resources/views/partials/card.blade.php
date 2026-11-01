<button type="button" @class(['kcard', 'open' => $openCard === $card->id, 'done' => $card->isDone(), 'todo' => $card->isTodo()]) wire:key="card-{{ $card->id }}" wire:click="open('{{ $card->id }}')">
	<span class="kline">
		@if ($card->isHighPriority())<svg class="ic s prio" width="11" height="11" role="img" aria-label="{{ __('Hoge prioriteit') }}"><use href="#i-prio"/></svg>@endif
		<span class="kkey">{{ $card->ticketKey ?? ($card->isTodo() ? __('to-do') : __('zonder ticket')) }}</span>
		@if ($card->project)<span>·</span><span class="kproject">{{ $card->project }}</span>@else<span class="kproject"></span>@endif
		<span>{{ $card->ageLabel() }}</span>
	</span>
	<span class="ktitle">{{ $card->title }}</span>
	<span class="kstatus">
		@if ($card->lensStates !== [])
			<span class="ldots" aria-hidden="true">@foreach ($card->lensStates as $state)<span class="ldot {{ $state }}"></span>@endforeach</span>
		@else
			<span class="sdot {{ $card->status->value }}"></span>
		@endif
		<span @class(['txt', 'yours' => $card->isYourTurn()])>{{ $card->statusText }}</span>
		@if ($card->behind > 0)
			<span class="wicon" title="{{ trans_choice(':count commit achter op :main|:count commits achter op :main', $card->behind, ['count' => $card->behind, 'main' => $card->mainBranch]) }}"><svg class="ic s" width="13" height="13" role="img" aria-label="{{ __('Achter op :main', ['main' => $card->mainBranch]) }}"><use href="#i-alert"/></svg></span>
		@endif
		@if ($card->isStale)
			<span class="wicon pause" title="{{ __('Stilgevallen') }}"><svg class="ic s" width="12" height="12" role="img" aria-label="{{ __('Stilgevallen') }}"><use href="#i-pause"/></svg></span>
		@endif
	</span>
</button>
