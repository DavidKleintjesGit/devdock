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
		@foreach (['Overzicht', 'Agent', 'Workflow', 'Worktree', 'Jira', 'Bitbucket'] as $name)
			<button type="button" role="tab" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" wire:click="setDrawerTab('{{ $name }}')">{{ __($name) }}</button>
		@endforeach
	</div>

	@if ($failure)
		<div class="dfail">
			<span class="note red"><x-icon name="alert" small/><span>{{ $failure }}</span></span>
			<button class="btn icon ghost" type="button" wire:click="clearFailure" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button>
		</div>
	@endif

	<div class="dbody">
		@switch ($tab)
			@case ('Agent')
				@include('partials.card.agent')
				@break
			@case ('Workflow')
				@include('partials.card.workflow')
				@break
			@case ('Worktree')
				@include('partials.card.worktree')
				@break
			@case ('Jira')
				@include('partials.card.jira')
				@break
			@case ('Bitbucket')
				@include('partials.card.bitbucket')
				@break
			@default
				@include('partials.card.overzicht')
		@endswitch
	</div>
</aside>
