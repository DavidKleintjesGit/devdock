<div class="modal-wrap" wire:click.self="cancelCopy">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="copy-title">
		<div class="mhead">
			<h2 id="copy-title">{{ __('Overnemen in een project') }}</h2>
			<button class="btn icon ghost close" type="button" wire:click="cancelCopy" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>

		<div class="mbody">
			<div class="field">
				<span class="eyebrow">{{ __('Wat') }}</span>
				<span class="mono">{{ $what }}</span>
			</div>

			<div class="field">
				<label class="eyebrow" for="copy-to">{{ __('Waarheen') }}</label>
				<select id="copy-to" class="input" wire:model.live="copyTo">
					<option value="">{{ __('Kies een project') }}</option>
					@foreach ($projects as $name)
						<option value="{{ $name }}">{{ $name }}</option>
					@endforeach
				</select>
			</div>

			@if ($warning)
				<span class="note amber"><x-icon name="alert" small/><span>{{ $warning }}</span></span>
			@endif
		</div>

		<div class="mfoot">
			<span class="note-left">{{ __('Dit kopieert alleen; er wordt nooit iets weggehaald.') }}</span>
			<button class="btn" type="button" wire:click="cancelCopy">{{ __('Annuleren') }}</button>
			<x-busy-button action="copy" class="btn primary" icon="copy" :busy="__('Overnemen...')" :disabled="$copyTo === ''">{{ __('Overnemen') }}</x-busy-button>
		</div>
	</div>
</div>
