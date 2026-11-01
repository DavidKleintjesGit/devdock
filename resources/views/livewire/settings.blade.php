<div>
	@if ($open)
		<div class="settings-wrap" wire:click.self="close">
			<div class="settings" role="dialog" aria-modal="true" aria-labelledby="settings-title">
				<div class="dtop">
					<h2 id="settings-title">{{ __('Instellingen') }}</h2>
					<button class="icon-btn" type="button" wire:click="close" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
				</div>

				<div class="settings-main">
					<nav class="settings-rail" aria-label="{{ __('Onderdelen') }}">
						@foreach ($sections as $heading => $items)
							<span class="eyebrow">{{ __($heading) }}</span>
							@foreach ($items as $key => [$label, $icon])
								<button type="button" wire:click="showSection('{{ $key }}')" @if ($section === $key) aria-current="page" @endif><x-icon :name="$icon" small/>{{ $label }}</button>
							@endforeach
						@endforeach
					</nav>

					<div class="settings-body">
						@include('partials.settings.' . $section)
					</div>
				</div>

				<div class="dfoot">
					@if ($section === 'over')
						<span class="hint">{{ __('Hier valt niets in te stellen.') }}</span>
						<button class="btn" type="button" wire:click="close">{{ __('Sluiten') }}</button>
					@else
						<span class="hint">{{ $saved ? __('Opgeslagen om :time in .env.', ['time' => $savedAt]) : __('Wordt opgeslagen in .env.') }}</span>
						<button class="btn text" type="button" wire:click="close">{{ __('Sluiten') }}</button>
						<x-busy-button action="save" class="btn primary" :busy="__('Opslaan...')">{{ __('Opslaan') }}</x-busy-button>
					@endif
				</div>
			</div>
		</div>
	@endif
</div>
