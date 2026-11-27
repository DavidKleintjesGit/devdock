<div class="dialog-wrap" wire:click.self="$set('targetDialog', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="target-title">
		<div class="dtop">
			<h2 id="target-title">{{ __('Waar bouwt deze taak op voort?') }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('targetDialog', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p><span class="mono">{{ $worktree->branch }}</span> {{ __('merget nu in :target.', ['target' => $worktree->targetBranch]) }}</p>
			<div class="choices">
				<label class="choice">
					<input type="radio" name="target" value="{{ $worktree->mainBranch }}" wire:model.live="targetChoice">
					<span class="radio"></span>
					<span><b class="mono" style="font-weight:500">{{ $worktree->mainBranch }}</b><small>{{ __('de hoofdlijn') }}</small></span>
				</label>
				@foreach ($taskTargets as $taskCard)
					<label class="choice" wire:key="target-{{ $taskCard->branch }}">
						<input type="radio" name="target" value="{{ $taskCard->branch }}" wire:model.live="targetChoice">
						<span class="radio"></span>
						<span><b class="mono" style="font-weight:500">{{ $taskCard->branch }}</b><small>{{ $taskCard->title }}</small></span>
					</label>
				@endforeach
			</div>
			<div class="field">
				<label for="target-branch" class="label-text">{{ __('Of een andere branch op origin') }}</label>
				<select id="target-branch" class="input mono" wire:model.live="targetChoice">
					<option value="{{ $worktree->mainBranch }}">{{ __('Kies een branch') }}</option>
					@foreach ($remoteBranches as $remote)
						@continue(substr($remote, strlen('origin/')) === $worktree->branch)
						<option value="{{ substr($remote, strlen('origin/')) }}">{{ substr($remote, strlen('origin/')) }}</option>
					@endforeach
				</select>
			</div>
			<span class="hint">{{ __('DevDock rebaset de eigen commits van deze taak op de gekozen branch en zet de pull request erop. Werk dat nog niet gecommit is gaat mee; stuit een rebase op conflicten, dan lost de agent ze op.') }}</span>
		</div>
		<div class="dfoot">
			<span class="hint">{{ __('Pushen blijft jouw knop') }}</span>
			<button class="btn text" type="button" wire:click="$set('targetDialog', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="saveTarget" @disabled($targetChoice === '' || $targetChoice === $worktree->targetBranch)>{{ __('Verplaatsen') }}</button>
		</div>
	</div>
</div>
