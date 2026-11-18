<div class="changes-window" x-data="codeReader" :data-code-theme="theme">
<div class="code-reader" x-data="viewedFiles(@js($cardObject?->id ?? $this->card), @js($reader['diffFingerprints']))">
	<div class="dbar" x-data="{ hasChanged: false }" x-init="setInterval(async () => { if (! hasChanged && ! document.hidden) hasChanged = await $wire.lookForChanges() }, 5000)">
		<span class="kind">{{ __('Wijzigingen') }} · {{ $cardObject?->ticketKey ?? $cardObject?->title ?? $this->card }}</span>
		<button class="btn sm primary" type="button" x-show="hasChanged" x-cloak wire:click="renew" x-on:click="hasChanged = false"><x-icon name="refresh" small/>{{ __('Gewijzigd · vernieuwen') }}</button>
		@include('partials.code-theme-switch')
		<button class="icon-btn" type="button" x-show="! hasChanged" wire:click="renew" wire:loading.class="is-busy" wire:target="renew" aria-label="{{ __('Vernieuwen') }}" title="{{ __('Vernieuwen') }}"><x-icon name="refresh"/></button>
	</div>

	@if ($hasWorktree)
		@include('partials.diff-reader', $reader)
	@else
		<p class="hint" style="padding:24px">{{ __('Deze kaart heeft geen werkkopie meer.') }}</p>
	@endif
</div>
</div>
