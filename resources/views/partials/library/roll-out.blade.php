<div class="dialog-wrap" wire:click.self="cancelRollOut">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="rollout-title">
		<div class="dtop">
			<h2 id="rollout-title">{{ trans_choice('Bijwerken in :count project?|Bijwerken in :count projecten?', count($rollOutProjects), ['count' => count($rollOutProjects)]) }}</h2>
			<button class="icon-btn" type="button" wire:click="cancelRollOut" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('De nieuwste versie komt over wat er staat. DevDock bewaart eerst de oude inhoud, dus je kunt het altijd terugdraaien.') }}</p>
			<div class="list">
				@foreach ($rollOutProjects as $rollOutProject)
					<label class="tick row" style="min-height:44px" wire:key="ro-{{ $rollOutProject }}">
						<input type="checkbox" checked wire:click="toggleRollOutProject('{{ $rollOutProject }}')">
						<span class="mark"></span>
						<b>{{ $rollOutProject }}</b>
					</label>
				@endforeach
			</div>
			<span class="hint">{{ __('Een taak die nu loopt heeft zijn eigen werkkopie en merkt hier niets van tot je die bijwerkt.') }}</span>
		</div>
		<div class="dfoot">
			<span class="hint">{{ __('Alleen bestanden schrijven; committen doe je per project') }}</span>
			<button class="btn text" type="button" wire:click="cancelRollOut">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="rollOut" @disabled($rollOutProjects === [])>{{ __('Bijwerken') }}</button>
		</div>
	</div>
</div>
