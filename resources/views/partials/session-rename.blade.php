<div class="modal-wrap" wire:click.self="cancelRename">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="rename-title">
		<div class="mhead">
			<h2 id="rename-title">{{ __('Sessie hernoemen') }}</h2>
			<button class="btn icon ghost close" type="button" wire:click="cancelRename" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>

		<div class="mbody">
			<div class="field">
				<label class="eyebrow" for="session-title">{{ __('Naam') }}</label>
				<input id="session-title" class="input" type="text" wire:model="newTitle" wire:keydown.enter="rename('{{ $session->uuid }}')" autocomplete="off">
				@error('newTitle')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
			</div>
			<span class="muted" style="font-size:12.5px">{{ __('Dit schrijft een regel achter in het sessiebestand; Claude Code leest de laatste naam.') }}</span>
		</div>

		<div class="mfoot">
			<button class="btn" type="button" wire:click="cancelRename">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="rename('{{ $session->uuid }}')">{{ __('Hernoemen') }}</button>
		</div>
	</div>
</div>
