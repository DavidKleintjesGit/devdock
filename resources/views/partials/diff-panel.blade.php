<div class="scrim" style="z-index:65" wire:click="closeDiff"></div>
<aside class="drawer wide over diff-drawer" role="dialog" aria-labelledby="diff-title" x-data="viewedFiles(@js($card->id), @js($diffFingerprints))" x-init="$nextTick(() => document.getElementById(@js($openAnchor))?.scrollIntoView({ block: 'start' }))">
	<div class="resize-edge" data-resize="--diff-width" data-edge="start" data-min="560" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
	<div class="dbar">
		<span class="kind" id="diff-title">{{ __('Wijzigingen') }} · {{ $card->ticketKey ?? $card->title }}</span>
		<span class="grow"></span>
		<button class="icon-btn" type="button" x-on:click="window.open(@js(route('changes', ['card' => $card->id, 'view' => $diffView->value, 'run' => $roundRun?->promptRunId->id])), @js('devdock-changes-' . md5($card->id)), 'popup,width=1440,height=900'); $wire.closeDiff()" aria-label="{{ __('In eigen venster') }}" title="{{ __('In eigen venster') }}"><x-icon name="external"/></button>
		<button class="icon-btn" type="button" wire:click="closeDiff" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	@include('partials.diff-reader')
</aside>
