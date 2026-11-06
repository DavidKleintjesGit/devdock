@if (! $worktree && $mainCheckout)
	<div class="dbody">
		@include('partials.card.main-checkout', ['canCommitHere' => false])
	</div>
@elseif (! $worktree && $externalPlace)
	<div class="dbody">
		<section class="dsec">
			<div class="external-place">
				<b><x-icon name="branch" small/>{{ __(':branch staat elders uitgecheckt', ['branch' => $card->branch]) }}</b>
				<span>{{ __('In') }} <span class="mono">{{ $externalPlace['path'] }}</span>.</span>
				<span class="hint">{{ __('Git staat één map per branch toe, en DevDock laat een agent alleen werken in een kopie die het zelf maakte. Zet die map op een andere branch of haal hem weg; dan krijgt deze taak een eigen werkkopie.') }}</span>
				<div class="external-actions">
					<x-busy-button action="$refresh" class="btn sm" :busy="__('Nakijken...')">{{ __('Opnieuw kijken') }}</x-busy-button>
				</div>
			</div>
		</section>
	</div>
@elseif (! $worktree)
	<div class="dbody">
		<section class="dsec">
			<div class="empty">
				<b>{{ __('Nog geen werkkopie') }}</b>
				<span>{{ __('Een eigen map voor deze taak, naast het project, zodat er meer taken tegelijk kunnen lopen.') }}</span>
			</div>
		</section>
		@include('partials.card.new-worktree')
	</div>

	<div class="dfoot">
		<span class="hint">{{ $committed ?? __('De branch wordt van origin afgesneden zonder hem te volgen.') }}</span>
		<x-busy-button action="createWorktree" class="btn primary" :busy="__('Aanmaken...')" :disabled="$card->project === null">{{ __('Werkkopie aanmaken') }}</x-busy-button>
	</div>
@else
	@switch ($subtab?->value)
		@case ('branch')
			@include('partials.card.werkkopie.branch')
			@break
		@case ('inrichting')
			@include('partials.card.werkkopie.inrichting')
			@break
		@case ('kopie')
			@include('partials.card.werkkopie.kopie')
			@break
		@default
			@include('partials.card.werkkopie.wijzigingen')
	@endswitch

	@if ($databaseDialog === 'new' && $database)
		@include('partials.card.database-new')
	@elseif ($databaseDialog === 'choose' && $database)
		@include('partials.card.database-choose')
	@endif

	@if ($upstreamDialog)
		@include('partials.card.upstream')
	@endif
@endif
