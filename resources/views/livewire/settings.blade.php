<div>
	@if ($open)
		<div class="modal-wrap" wire:click.self="close">
			<div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="settings-title">
				<div class="mhead">
					<h2 id="settings-title">{{ __('Instellingen') }}</h2>
					<button class="btn icon ghost close" type="button" wire:click="close" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
				</div>

				<div class="setsplit">
					<nav class="setrail" aria-label="{{ __('Instellingen') }}">
						@foreach ($sections as $heading => $items)
							<span class="railhead">{{ __($heading) }}</span>
							@foreach ($items as $key => [$label, $icon])
								<button type="button" wire:click="showSection('{{ $key }}')" @if ($section === $key) aria-current="page" @endif><x-icon :name="$icon" small/>{{ $label }}</button>
							@endforeach
						@endforeach
					</nav>

					<div class="setpanel">
						@include('partials.settings.' . $section)
					</div>
				</div>

				<div class="mfoot">
					@if ($saved)
						<span class="note-left saved"><x-icon name="check" small/>{{ __('Opgeslagen om :time in .env.', ['time' => $savedAt]) }}</span>
					@else
						<span class="note-left">{{ __('Wijzigingen worden naar .env geschreven.') }}</span>
					@endif
					<button class="btn" type="button" wire:click="close">{{ __('Sluiten') }}</button>
					<button class="btn primary" type="button" wire:click="save">
						<span wire:loading.remove wire:target="save">{{ __('Opslaan') }}</span>
						<span wire:loading wire:target="save">{{ __('Bezig...') }}</span>
					</button>
				</div>
			</div>
		</div>
	@endif
</div>
