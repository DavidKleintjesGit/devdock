<aside class="drawer card-drawer" role="dialog" aria-labelledby="drawer-title" data-card="{{ $card->id }}" data-view="{{ $tab }}:{{ $subtab?->value }}" x-data="{
	tall: $persist(false).as('devdock.chat-tall'),
	pendingTab: null,
	pendingView: null,
	compact: false,
	// folds the card head away while you read further down; it only folds when the body stays scrollable without it, or folding and unfolding would trigger each other
	follow(body) {
		if (! body.classList?.contains('dbody') || this.$el.querySelector('.composer')) {
			return;
		}

		if (this.compact) {
			this.compact = body.scrollTop >= 4;

			return;
		}

		const headHeight = this.$el.querySelector('.card-head')?.offsetHeight ?? 0;

		this.compact = body.scrollTop > 48 && body.scrollHeight - body.clientHeight > headHeight + 48;
	},
}" :class="{ 'chat-tall': tall, switching: pendingView, 'switching-tab': pendingTab, compact }" @drawer-card.window="pendingTab = null; pendingView = null; $nextTick(() => keepTabGhost($el))" @scroll.capture="follow($event.target)">
	<div class="dbar">
		@if ($previous)
			<button class="back kind" type="button" wire:click="back"><x-icon name="back" small/>{{ $previous }}</button>
		@else
			<span class="kind">{{ __('Kaart') }} · {{ $card->ticketKey === null ? ($card->isTodo() ? __('to-do') : $card->project) : $card->id }}</span>
		@endif

		<span class="compact-title">
			@if ($card->ticketKey)<span class="mono">{{ $card->ticketKey }}</span>@endif
			<b>{{ $card->title }}</b>
			@if ($currentStep)<span class="step-pill"><span class="circle"></span>{{ __(':step · stap :number van :total', ['step' => $currentStep['label'], 'number' => $currentStep['number'], 'total' => count($cardSteps)]) }}</span>@endif
		</span>

		@if ($tab === 'Agent')
			<span class="chat-title">
				@if ($card->ticketKey)<span class="mono">{{ $card->ticketKey }}</span>@endif
				<b>{{ __('Chat') }} · {{ $card->title }}</b>
			</span>
			<button class="icon-btn outlined chat-shrink" type="button" @click="tall = false" aria-label="{{ __('Chat verkleinen') }}" title="{{ __('Chat verkleinen') }}"><x-icon name="shrink"/></button>
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
				@if ($card->isCleanedUp)
					<button type="button" role="menuitem" wire:click="reopen">{{ __('Heropenen') }}</button>
				@endif
				<button type="button" role="menuitem" @click="open = false; $dispatch('clean-up-cards', { cards: [@js($card->id)], action: 'clean_up' })">{{ __('Opruimen') }}</button>
				<button type="button" role="menuitem" @click="open = false; $dispatch('clean-up-cards', { cards: [@js($card->id)], action: 'archive' })">{{ __('Archiveren') }}</button>
				@if ($card->isTodo())
					<button class="danger" type="button" role="menuitem" @click="open = false; $dispatch('clean-up-cards', { cards: [@js($card->id)], action: 'delete' })">{{ __('Verwijderen') }}</button>
				@endif
			</div>
		</details>
		<button class="icon-btn" type="button" @click="document.body.classList.add('drawer-closing')" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead card-head">
		<div class="title-block">
			<span class="meta">
				@if ($card->ticketKey)
					@if ($card->ticketUrl)<a class="mono" href="{{ $card->ticketUrl }}" target="_blank" rel="noopener" style="text-decoration:none">{{ $card->ticketKey }}</a>@else<span class="mono">{{ $card->ticketKey }}</span>@endif
					@if ($card->variant())<span class="label teal" title="{{ __('Aftakking van :ticket', ['ticket' => $card->ticketKey]) }}">{{ __('aftakking') }} · {{ $card->variant() }}</span>@endif
				@endif
				@if ($card->ticketStatus)<span class="label amber">{{ $card->ticketStatus }}</span>@endif
				@if ($card->isHighPriority())<span class="label red">{{ __('Hoge prioriteit') }}</span>@endif
			</span>
			<h2 id="drawer-title">{{ $card->title }}</h2>
			@if ($isSetup)<p class="sub" style="margin:0">{{ __('Nog niet ingericht. Drie dingen staan vast voordat er gewerkt kan worden.') }}</p>@endif
		</div>
		@unless ($isSetup)
			<ol class="steps" aria-label="{{ __('Stappen') }}">
				@foreach ($cardSteps as $cardStep)
					<li class="{{ $cardStep['state'] }}" @if ($cardStep['state'] === 'now') aria-current="step" @endif>
						<span class="circle">@if ($cardStep['state'] === 'done')<x-icon name="check" small/>@endif</span>
						<span class="name">{{ $cardStep['label'] }}</span>
					</li>
				@endforeach
			</ol>
		@endunless
	</div>

	@if ($isSetup)
		@include('partials.card.taak-balk')
		@include('partials.card.inrichten')
	@else
		<nav class="tabs card-tabs" role="tablist" aria-label="{{ __('Panelen') }}">
			@foreach ($drawerTabs as $drawerTab)
				<button type="button" role="tab" aria-selected="{{ $tab === $drawerTab['tab']->value ? 'true' : 'false' }}" :aria-selected="(pendingTab ?? @js($tab)) === @js($drawerTab['tab']->value)" @click="if (@js($tab) !== @js($drawerTab['tab']->value)) { pendingTab = @js($drawerTab['tab']->value); pendingView = pendingTab; compact = false; showTabGhost($el.closest('.card-drawer'), pendingTab) }" wire:click="setDrawerTab('{{ $drawerTab['tab']->value }}')" aria-label="{{ $drawerTab['tab']->label() }}">
					<x-icon :name="$drawerTab['tab']->icon()"/>
					<span class="tab-label">{{ $drawerTab['tab']->label() }}</span>
					@if ($drawerTab['tones'] !== [])
						<x-hint-dot :tones="$drawerTab['tones']"/>
					@elseif ($drawerTab['unread'] > 0)
						<span class="tunread" title="{{ trans_choice(':count nieuwe melding|:count nieuwe meldingen', $drawerTab['unread'], ['count' => $drawerTab['unread']]) }}">{{ $drawerTab['unread'] }}</span>
					@endif
				</button>
			@endforeach
		</nav>

		@include('partials.card.meldingen')

		@if ($subtab)
			<div class="subbar">
				<nav class="segmented" aria-label="{{ __($tab) }}">
					@foreach ($tabSubtabs as $subtabRow)
						<button type="button" @if ($subtab === $subtabRow['subtab']) aria-current="page" @endif :aria-current="((pendingView ?? @js($tab . ':' . $subtab->value)) === @js($tab . ':' . $subtabRow['subtab']->value)) ? 'page' : null" @click="pendingView = @js($subtab === $subtabRow['subtab'] ? null : $tab . ':' . $subtabRow['subtab']->value); compact = false; if (pendingView) showTabGhost($el.closest('.card-drawer'), pendingView)" wire:click="setDrawerSubtab('{{ $subtabRow['subtab']->value }}')">
							<x-hint-dot :tones="$subtabRow['tones']"/>
							{{ $subtabRow['subtab']->label() }}
							@if ($subtabRow['count'] > 0)<span class="note">{{ $subtabRow['count'] }}</span>@endif
						</button>
					@endforeach
				</nav>
				<span class="grow"></span>
				@if ($subtab->value === 'gesprek')
					<button class="icon-btn outlined chat-grow" type="button" @click="tall = true" aria-label="{{ __('Chat over de hele hoogte') }}" title="{{ __('Chat over de hele hoogte') }}"><x-icon name="tall"/></button>
				@endif
			</div>
		@endif

		@include('partials.skeletons.tab')
		{{-- how a tab looked the last time, shown at once until the fresh one arrives --}}
		<div class="dbody tab-ghost" wire:ignore x-ignore aria-hidden="true" inert></div>

		@switch ($tab)
			@case ('Agent')
				@include($subtab?->value === 'context' ? 'partials.card.context' : 'partials.card.agent')
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
