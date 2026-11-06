<div class="dbody workspace">
	@if (! $worktree->origin->isOurs())
		<div class="external-note">
			<div class="external-where">
				<span class="label blue">{{ __('extern') }} · {{ $worktree->origin->label() }}</span>
				<span class="mono">{{ $worktree->path }}</span>
			</div>
		</div>
	@endif

	@if ($mainCheckout)
		@include('partials.card.main-checkout', ['canCommitHere' => true])
	@endif

	<div class="fact-grid">
		<div class="kv">
			<span class="k">{{ __('Map') }}</span>
			<span class="v"><span class="grow">{{ $worktree->path }}</span><button class="btn xs" type="button" wire:click="openInClaude">{{ __('Openen') }}</button></span>
			<span class="hint">{{ __('Gemaakt op :date.', ['date' => $worktree->createdAt?->toDisplayZone()->translatedFormat('j F')]) }}@if ($worktree->site) · <a href="{{ $worktree->url() }}" target="_blank" rel="noopener">{{ $worktree->site }}</a>@endif</span>
		</div>

		@if ($database)
			<div class="kv">
				<span class="k">{{ __('Database') }}</span>
				<span class="v"><span class="grow">{{ $database->isSqlite() ? basename((string) $database->database) : $database->database }}</span>@if ($database->isShared)<span class="label amber">{{ __('gedeeld met het project') }}</span>@else<span class="label green">{{ __('eigen') }}</span>@endif</span>
				<span class="hint">{{ $database->serverLabel() }} · {{ __('uit de .env van deze kopie') }}</span>
				@if ($database->isShared)
					<span class="hint" style="color:var(--amber)">{{ __('Een migratie hier verandert ook de database van het project en van andere werkkopieën.') }}</span>
				@endif
				<span class="v" style="margin-top:4px">
					<button class="btn xs" type="button" wire:click="startDatabase">{{ __('Eigen database aanmaken') }}</button>
					<button class="btn xs" type="button" wire:click="startChooseDatabase">{{ __('Een andere kiezen') }}</button>
				</span>
			</div>
		@endif

		@if ($testDatabase)
			<div class="kv">
				<span class="k">{{ __('Testdatabase') }}</span>
				<span class="v"><span class="grow">{{ $testDatabase->database }}</span>@if ($testDatabase->isShared)<span class="label amber">{{ __('gedeeld met het project') }}</span>@else<span class="label green">{{ __('eigen') }}</span>@endif</span>
				<span class="hint">{{ $testDatabase->serverLabel() }} · {{ __('uit de .env.testing van deze kopie') }}</span>
				@if ($testDatabase->isShared)
					<span class="hint" style="color:var(--amber)">{{ __('Draaien hier en ergens anders tegelijk tests, dan legen ze elkaars database.') }}</span>
					<span class="v" style="margin-top:4px"><x-busy-button action="giveOwnTestDatabase" class="btn xs" :busy="__('Aanmaken...')">{{ __('Eigen testdatabase aanmaken') }}</x-busy-button></span>
				@endif
			</div>
		@endif

		<div class="kv">
			<span class="k">{{ __('Upstream, hier gaat een push heen') }}</span>
			<span class="v"><span class="grow">{{ $worktree->upstream ?? __('nog niets; de eerste push maakt origin/:branch aan', ['branch' => $worktree->branch]) }}</span><button class="btn xs" type="button" wire:click="startUpstream">{{ __('Wijzigen') }}</button></span>
			<span class="hint">{{ __(':ahead voor, :behind achter op :main', ['ahead' => $worktree->ahead, 'behind' => $worktree->behind, 'main' => $worktree->targetBranch]) }}</span>
		</div>

		<div class="kv">
			<span class="k">{{ __('Gemaakt vanaf') }}</span>
			<span class="v"><span class="grow">origin/{{ $worktree->targetBranch }}</span></span>
			@if ($worktree->behind > 0)<span class="hint">{{ __('Inmiddels :count commits achter. Rebasen staat bovenaan.', ['count' => $worktree->behind]) }}</span>@endif
		</div>

		@if ($packages !== [])
			<div class="kv">
				<span class="k">{{ __('Pakketten') }}</span>
				@foreach ($packages as $package)
					<span class="v"><span class="grow">{{ $package['folder'] }}</span><span class="hint">{{ $package['isBorrowed'] ? __('komt uit het project') : ($package['isSameLock'] ? __('nog niet geleend') : __('eigen installatie nodig')) }}</span></span>
				@endforeach
				<span class="hint">{{ __('Niet opnieuw geïnstalleerd zolang het slotbestand gelijk is aan dat van het project. Wijkt het af, dan installeert DevDock ze hier apart.') }}</span>
			</div>
		@endif

		<div class="kv">
			<span class="k">{{ __('Overgenomen uit het project') }} · {{ trans_choice(':count bestand dat git niet meeneemt|:count bestanden die git niet meeneemt', count($carried), ['count' => count($carried)]) }}</span>
			<span class="chips">@foreach ($carried as $entry)<span class="path">{{ $entry }}</span>@endforeach</span>
			<span class="v"><x-busy-button action="carryOverAgain" class="btn xs" :busy="__('Overnemen...')">{{ __('Opnieuw overnemen') }}</x-busy-button></span>
		</div>

		<div class="kv">
			<span class="k">{{ __('Mag ook inzien') }}</span>
			<span class="v"><span class="grow">{{ $readableProjects === [] ? __('alleen zijn eigen werkkopie') : implode(', ', $readableProjects) }}</span><button class="btn xs" type="button" wire:click="setDrawerTab('Koppelingen')">{{ __('Wijzigen') }}</button></span>
			<span class="hint">{{ __('De agent mag daar lezen, niet schrijven.') }}</span>
		</div>

		<div class="kv">
			<span class="k">{{ __('Opruimen') }}</span>
			<span class="hint">{{ $card->isDone() ? __('De pull request is gemerged. Dit gaat weg:') : __('Kan pas als de pull request gemerged is. Dan gaat dit weg:') }}</span>
			<ul class="cleanup">
				@foreach ($cleanUp as $line)<li>{{ $line }}</li>@endforeach
			</ul>
			<span class="v"><x-busy-button action="removeWorktree" class="btn xs" :busy="__('Opruimen...')" :disabled="! $card->isDone()">{{ __('Opruimen') }}</x-busy-button></span>
		</div>
	</div>
</div>
