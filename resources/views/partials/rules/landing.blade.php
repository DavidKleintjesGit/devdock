<div class="dialog-wrap" wire:click.self="$set('choosingLanding', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="landing-title">
		<div class="dtop">
			<h2 id="landing-title">{{ __('Waar landen regels in :project?', ['project' => $project]) }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('choosingLanding', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('Een bibliotheekregel wordt in dit project op deze plek gezet. DevDock heeft gekeken wat er al staat.') }}</p>
			<div class="choices">
				@foreach ($cases as $case)
					<label class="choice" wire:key="landing-{{ $case->value }}">
						<input type="radio" name="landing" value="{{ $case->value }}" wire:model.live="landingChoice">
						<span class="radio"></span>
						<span class="grow" style="display:flex;flex-direction:column;gap:4px">
							<span style="display:flex;gap:8px;align-items:center"><b class="mono" style="font-weight:500">{{ $case->label() }}</b>
								@if ($case->isFound())<span class="label {{ $existing[$case->value] ? 'green' : '' }}">{{ $existing[$case->value] ? __('gevonden') : __('bestaat niet') }}</span>@endif
							</span>
							<small>{{ $case->explanation() }}</small>
							@if (! $case->isFound() && $landingChoice === $case->value)
								<input class="input mono" type="text" wire:model="landingFolder" placeholder="docs/regels">
							@endif
						</span>
					</label>
				@endforeach
			</div>
		</div>
		<div class="dfoot">
			<span class="hint">{{ __('Geldt voor regels die je hierna toevoegt of bijwerkt') }}</span>
			<button class="btn text" type="button" wire:click="$set('choosingLanding', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="saveLanding">{{ __('Opslaan') }}</button>
		</div>
	</div>
</div>
