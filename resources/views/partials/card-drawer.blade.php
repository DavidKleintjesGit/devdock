<aside class="drawer" role="dialog" aria-labelledby="drawer-title" x-data="{ tall: $persist(false).as('devdock.chat-tall'), pendingTab: null }" :class="{ 'chat-tall': tall, switching: pendingTab }" @drawer-card.window="pendingTab = null">
	<div class="dbar">
		@if ($previous)
			<button class="back kind" type="button" wire:click="back"><x-icon name="back" small/>{{ $previous }}</button>
		@else
			<span class="kind">{{ __('Kaart') }} · {{ $card->ticketKey ?? ($card->isTodo() ? __('to-do') : $card->project) }}</span>
		@endif

		@if ($tab === 'Agent')
			<span class="chat-title">
				@if ($card->ticketKey)<span class="mono">{{ $card->ticketKey }}</span>@endif
				<b>{{ __('Agent') }} · {{ $card->title }}</b>
			</span>
			<button class="icon-btn chat-shrink" type="button" @click="tall = false" aria-label="{{ __('Chat verkleinen') }}" title="{{ __('Chat verkleinen') }}"><x-icon name="shrink"/></button>
		@endif
		<details class="menu" x-data="{ open: false }" :open="open" @click.outside="open = false">
			<summary class="icon-btn filled" aria-label="{{ __('Acties') }}" @click.prevent="open = ! open"><x-icon name="meer"/></summary>
			<div class="menu-pop" role="menu">
				@if ($worktree || $card->agent)
					<button type="button" role="menuitem" wire:click="openInClaude">{{ __('Openen in Claude Code') }}</button>
				@endif
				@if ($card->branch)
					<button type="button" role="menuitem" wire:click="$set('renaming', true)">{{ __('Branch hernoemen') }}</button>
				@endif
				@if ($card->isTodo())
					<button type="button" role="menuitem" wire:click="planLater({{ $card->isInBacklog() ? 'false' : 'true' }})">{{ $card->isInBacklog() ? __('In de sprint zetten') : __('Naar de backlog') }}</button>
				@endif
				<hr>
				<button class="danger" type="button" role="menuitem" wire:click="deleteTask">{{ $confirmingDeleteTask ? __('Zeker weten? Verwijderen') : __('Taak verwijderen') }}</button>
			</div>
		</details>
		<button class="icon-btn" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<span class="meta">
			@if ($card->ticketKey)
				@if ($card->ticketUrl)<a class="mono" href="{{ $card->ticketUrl }}" target="_blank" rel="noopener" style="text-decoration:none">{{ $card->ticketKey }}</a>@else<span class="mono">{{ $card->ticketKey }}</span>@endif
			@endif
			@if ($card->ticketStatus)<span class="label amber">{{ $card->ticketStatus }}</span>@endif
			@if ($card->isHighPriority())<span class="label red">{{ __('Hoge prioriteit') }}</span>@endif
		</span>
		<h2 id="drawer-title">{{ $card->title }}</h2>
		@if ($isSetup)<p class="sub" style="margin:0">{{ __('Nog niet ingericht. Drie dingen staan vast voordat er gewerkt kan worden.') }}</p>@endif
	</div>

	@if ($isSetup)
		@include('partials.card.inrichten')
	@else
		<ol class="steps" aria-label="{{ __('Stappen') }}">
			@foreach ($cardSteps as $cardStep)
				<li class="{{ $cardStep['state'] }}" @if ($cardStep['state'] === 'now') aria-current="step" @endif>
					<span class="track">
						<span @class(['line', 'none' => $loop->first, 'on' => $cardStep['state'] !== 'todo'])></span>
						<span class="circle">@if ($cardStep['state'] === 'done')<x-icon name="check" small/>@else{{ $cardStep['number'] }}@endif</span>
						<span @class(['line', 'none' => $loop->last, 'on' => $cardStep['state'] === 'done'])></span>
					</span>
					{{ $cardStep['label'] }}
				</li>
			@endforeach
		</ol>

		<nav class="tabs" role="tablist" aria-label="{{ __('Panelen') }}">
			@foreach (['Overzicht', 'Agent', 'Werkkopie', 'Review', 'Koppelingen', 'Context'] as $name)
				<button type="button" role="tab" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" :aria-selected="(pendingTab ?? @js($tab)) === @js($name)" @click="if (@js($tab) !== @js($name)) pendingTab = @js($name)" wire:click="setDrawerTab('{{ $name }}')">{{ __($name) }}@if ($card->unreadOnTab($name) > 0)<span @class(['tunread', 'question' => $name === 'Agent' && $card->hasUnreadQuestion]) title="{{ trans_choice(':count nieuwe melding|:count nieuwe meldingen', $card->unreadOnTab($name), ['count' => $card->unreadOnTab($name)]) }}">{{ $card->unreadOnTab($name) }}</span>@endif</button>
			@endforeach
			@if ($tab === 'Agent')
				<button class="icon-btn chat-grow" type="button" @click="tall = true" aria-label="{{ __('Chat vergroten') }}" title="{{ __('Chat vergroten') }}"><x-icon name="tall"/></button>
			@endif
		</nav>

		@if ($failure)
			<div class="alert red dfail">
				<x-icon name="alert" small/><span class="grow">{{ $failure }}</span>
				<button class="icon-btn" type="button" wire:click="clearFailure" aria-label="{{ __('Melding weg') }}" style="width:26px;height:26px"><x-icon name="x" small/></button>
			</div>
		@endif

		@include('partials.card.gereed-balk')

		@include('partials.skeletons.tab')

		@switch ($tab)
			@case ('Agent')
				@include('partials.card.agent')
				@break
			@case ('Werkkopie')
				@include('partials.card.werkkopie')
				@break
			@case ('Review')
				@include('partials.card.review')
				@break
			@case ('Koppelingen')
				@include('partials.card.koppelingen')
				@break
			@case ('Context')
				@include('partials.card.context')
				@break
			@default
				@include('partials.card.overzicht')
		@endswitch
	@endif
</aside>

@if ($renaming && $card->branch)
	<div class="dialog-wrap" wire:click.self="$set('renaming', false)">
		<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="rename-title">
			<div class="dtop">
				<h2 id="rename-title">{{ __('Branch hernoemen') }}</h2>
				<button class="icon-btn" type="button" wire:click="$set('renaming', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
			</div>
			<div class="dbody">
				<p>{{ __('Hernoemt de branch :branch, de map van de werkkopie en de koppelingen op de taak in één keer.', ['branch' => $card->branch]) }}</p>
				<div class="field">
					<label for="rename-branch">{{ __('Nieuwe naam') }}</label>
					<input id="rename-branch" class="input mono" type="text" wire:model="renameBranch" autocomplete="off">
				</div>
			</div>
			<div class="dfoot">
				<span class="hint">{{ $renameTo ? __('Voorstel: :name', ['name' => $renameTo]) : '' }}</span>
				<button class="btn text" type="button" wire:click="$set('renaming', false)">{{ __('Annuleren') }}</button>
				<x-busy-button action="renameToTicket" class="btn primary" :busy="__('Hernoemen...')">{{ __('Hernoemen') }}</x-busy-button>
			</div>
		</div>
	</div>
@endif
