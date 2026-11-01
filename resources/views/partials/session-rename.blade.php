<div class="dialog-wrap" wire:click.self="cancelRename">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="rename-session-title">
		<div class="dtop">
			<h2 id="rename-session-title">{{ __('Sessie hernoemen') }}</h2>
			<button class="icon-btn" type="button" wire:click="cancelRename" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>

		<div class="dbody">
			<div class="field">
				<label for="rename-session-name">{{ __('Naam') }}</label>
				<input id="rename-session-name" @class(['input', 'invalid' => $errors->has('newTitle')]) type="text" wire:model="newTitle" wire:keydown.enter="rename('{{ $session->uuid }}')" autocomplete="off">
				@error('newTitle')<span class="err">{{ $message }}</span>@enderror
			</div>
			<span class="hint">{{ __('Dit schrijft een regel achter in het sessiebestand; Claude Code leest de laatste naam.') }}</span>
		</div>

		<div class="dfoot">
			<span class="hint"></span>
			<button class="btn text" type="button" wire:click="cancelRename">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="rename('{{ $session->uuid }}')">{{ __('Hernoemen') }}</button>
		</div>
	</div>
</div>
