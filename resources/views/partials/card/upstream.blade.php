<div class="dialog-wrap" wire:click.self="$set('upstreamDialog', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="upstream-title">
		<div class="dtop">
			<h2 id="upstream-title">{{ __('Waar gaat een push heen?') }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('upstreamDialog', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('De lokale branch') }} <span class="mono">{{ $worktree->branch }}</span> {{ $worktree->upstream ? __('volgt nu :upstream.', ['upstream' => $worktree->upstream]) : __('volgt nu niets.') }}</p>
			<div class="choices">
				<label class="choice">
					<input type="radio" name="upstream" value="" wire:model.live="upstreamChoice">
					<span class="radio"></span>
					<span><b>{{ __('Nog niets volgen') }}</b><small>{{ __('De eerste push maakt origin/:branch aan en gaat hem daarna volgen.', ['branch' => $worktree->branch]) }}</small></span>
				</label>
				@foreach ($remoteBranches as $remote)
					<label class="choice" wire:key="remote-{{ $remote }}">
						<input type="radio" name="upstream" value="{{ $remote }}" wire:model.live="upstreamChoice">
						<span class="radio"></span>
						<span><b class="mono" style="font-weight:500">{{ $remote }}</b><small>{{ $remote === 'origin/' . $worktree->branch ? __('dezelfde naam · bestaat') : __('een andere branch op origin') }}</small></span>
					</label>
				@endforeach
			</div>
			<span class="hint">{{ __(':main en andere hoofdbranches staan niet in de lijst. Volgt een taakbranch de hoofdlijn, dan gaat een push daar rechtstreeks heen.', ['main' => $worktree->mainBranch]) }}</span>
		</div>
		<div class="dfoot">
			<span class="hint">{{ __('Er wordt niets gepusht') }}</span>
			<button class="btn text" type="button" wire:click="$set('upstreamDialog', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="saveUpstream">{{ __('Opslaan') }}</button>
		</div>
	</div>
</div>
