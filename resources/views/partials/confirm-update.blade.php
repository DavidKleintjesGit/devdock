<div class="modal-wrap" wire:click.self="cancelConfirm">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
		<div class="mhead">
			<h2 id="confirm-title">{{ __('Bestanden overschrijven?') }}</h2>
			<button class="btn icon ghost close" type="button" wire:click="cancelConfirm" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>

		<div class="mbody">
			@if ($confirming === 'core')
				<span class="note amber"><x-icon name="alert" small/><span>{{ __('De versie uit mtvtd-core komt over dit bestand heen. Wat je er zelf in hebt gezet gaat verloren.') }}</span></span>

				<dl class="facts">
					<div><dt>{{ __('Bestand') }}</dt><dd class="mono">{{ $file->path }}<small>{{ $file->project }}</small></dd></div>
					<div><dt>{{ __('Nu') }}</dt><dd>{{ __(':count regels', ['count' => $file->lines]) }}<small>{{ __('core heeft er :count', ['count' => $file->coreLines]) }}</small></dd></div>
				</dl>
			@else
				<span class="note amber"><x-icon name="alert" small/><span>{{ __('Elk achterlopend bestand in je selectie wordt overschreven met de versie uit mtvtd-core.') }}</span></span>

				<dl class="facts">
					<div><dt>{{ __('Bestanden') }}</dt><dd>{{ $behindInSelection }}</dd></div>
					<div><dt>{{ __('Projecten') }}</dt><dd>{{ $behindProjects }}</dd></div>
				</dl>
			@endif
		</div>

		<div class="mfoot">
			<span class="note-left">{{ __('Dit kun je niet ongedaan maken.') }}</span>
			<button class="btn" type="button" wire:click="cancelConfirm">{{ __('Annuleren') }}</button>
			<x-busy-button action="confirmUpdate" class="btn danger solid" icon="refresh" :busy="__('Overschrijven...')">{{ __('Overschrijven') }}</x-busy-button>
		</div>
	</div>
</div>
