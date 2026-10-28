<div class="actions">
	<button class="btn icon" type="button" wire:click="$refresh" aria-label="{{ __('Verversen') }}"><x-icon name="refresh"/></button>
	<button class="btn icon" type="button" onclick="Livewire.dispatch('open-settings')" aria-label="{{ __('Instellingen') }}"><x-icon name="tandwiel"/></button>
</div>
