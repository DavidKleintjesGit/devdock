<dl class="facts">
	<div><dt>{{ __('Status') }}</dt><dd>{{ $ticket?->status ?? __('geen ticket') }}</dd></div>
	<div><dt>{{ __('Prioriteit') }}</dt><dd>{{ $ticket?->priority ?? __('geen ticket') }}</dd></div>
	<div><dt>{{ __('Soort') }}</dt><dd>{{ $ticket?->type ?? __('geen ticket') }}</dd></div>
</dl>

<div class="field">
	<h3 class="eyebrow">{{ __('Omschrijving uit Jira') }}</h3>
	@if ($ticket?->description)
		<div class="prose">{{ $ticket->description }}</div>
	@elseif ($ticket)
		<div class="prose empty-prose">{{ __('Dit ticket heeft geen omschrijving.') }}</div>
	@else
		<div class="prose empty-prose">{{ __('Er hangt geen ticket aan deze taak, dus Jira heeft hier niets. Wat de agent als context krijgt staat op Overzicht.') }}</div>
	@endif
</div>

<div class="field">
	<div class="fieldhead">
		<h3 class="eyebrow">{{ __('Tickets') }}</h3>
		<span class="muted" style="font-size:12.5px">{{ __('één actief') }}</span>
	</div>
	@if ($ticketRows)
		<div class="linklist">
			@foreach ($ticketRows as $row)
				<div class="linkrow static">
					<span class="dot {{ $row['isActive'] ? 'green' : '' }}"></span>
					<span class="grow">
						<b class="mono">{{ $row['value'] }}</b>
						<small>{{ $row['isPinned'] ? __('vastgezet') : __('afgeleid uit de naam') }}</small>
					</span>
					@if ($row['isActive'])
						<span class="chip green">{{ __('actief') }}</span>
					@else
						<button class="btn sm" type="button" wire:click="activate('ticket', '{{ $row['value'] }}')">{{ __('Op actief zetten') }}</button>
					@endif
					@if ($row['isPinned'])
						<button class="btn icon ghost" type="button" aria-label="{{ __('Koppeling weghalen') }}" wire:click="unlink('ticket', '{{ $row['value'] }}')"><x-icon name="x" small/></button>
					@endif
				</div>
			@endforeach
		</div>
	@else
		<div class="prose empty-prose">{{ __('Nog geen ticket gekoppeld. Zet er hieronder één vast, of verzin een nummer als het ticket nog niet bestaat.') }}</div>
	@endif
	<div class="linkform">
		<div class="row">
			<input class="input" type="text" wire:model="linkTicket" value="{{ $linkTicket }}" placeholder="{{ __('Ticketnummer, bijvoorbeeld CSM-4120') }}">
			<button class="btn" type="button" wire:click="link('ticket')">{{ __('Ticket vastzetten') }}</button>
		</div>
		<span class="muted" style="font-size:12.5px">{{ __('Een ticketnummer mag je zelf verzinnen. Wat je hier vastzet blijft bij elkaar staan, ook als de namen niet op elkaar lijken.') }}</span>
	</div>
</div>

<div class="dfoot">
	<button class="btn primary" type="button" wire:click="fillPromptFrom('ticket')" wire:target="fillPromptFrom('ticket')" wire:loading.attr="disabled" @disabled(! $ticket)><x-icon name="chat" small/>{{ __('Prompt uit dit ticket') }}</button>
	@if ($card->ticketUrl)
		<a class="btn" href="{{ $card->ticketUrl }}" target="_blank" rel="noopener"><x-icon name="file" small/>{{ __('Openen in Jira') }}</a>
	@endif
	<span class="hint">{{ $ticket?->status ?? __('Zonder ticket') }}</span>
</div>
