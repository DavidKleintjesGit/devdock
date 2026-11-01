<header>
	<h3>{{ __('Bibliotheken') }}</h3>
	<p>{{ __('Sets regels en skills die DevDock met al hun versies bewaart. Welke bibliotheek een project volgt, stel je op Regels in.') }}</p>
</header>

<section>
	<div class="setlist">
		@foreach ($libraries as $libraryEntity)
			<div class="set stack" wire:key="set-lib-{{ $libraryEntity->id }}">
				@if ($editingLibrary === $libraryEntity->id)
					<label class="field"><span class="label-text">{{ __('Naam') }}</span><input class="input" type="text" wire:model="libraryName"></label>
					<label class="field"><span class="label-text">{{ __('Gekoppelde map voor regels') }}</span><input class="input mono" type="text" wire:model="libraryFolder" placeholder="{{ __('leeg: DevDock is de bron') }}"></label>
					<label class="field"><span class="label-text">{{ __('Gekoppelde map voor skills') }}</span><input class="input mono" type="text" wire:model="librarySkillsFolder" placeholder="{{ __('mag leeg blijven') }}"></label>
					<span style="display:flex;gap:8px;justify-content:flex-end">
						<button class="btn text sm" type="button" wire:click="$set('editingLibrary', null)">{{ __('Annuleren') }}</button>
						<button class="btn primary sm" type="button" wire:click="saveLibrary">{{ __('Opslaan') }}</button>
					</span>
				@else
					<span style="display:flex;align-items:flex-start;gap:16px">
						<span class="txt">
							<b>{{ $libraryEntity->name }}</b>
							<small>{{ trans_choice(':count regel|:count regels', count($libraryEntity->rules()), ['count' => count($libraryEntity->rules())]) }} {{ trans_choice('met :count versie|met :count versies', $libraryEntity->ruleVersionCount(), ['count' => $libraryEntity->ruleVersionCount()]) }} · {{ $libraryEntity->skills() === [] ? __('nog geen skills') : trans_choice(':count skill|:count skills', count($libraryEntity->skills()), ['count' => count($libraryEntity->skills())]) }}</small>
							<small>{{ __('Gekoppelde map:') }} <span class="mono">{{ $libraryEntity->folder ?? __('geen; DevDock is de bron') }}</span>{{ $libraryEntity->folder ? ' · ' . __('wijzigingen komen binnen als nieuwe versie') : '' }}</small>
							<small>{{ trans_choice(':count project volgt hem|:count projecten volgen hem', count($libraryEntity->followers), ['count' => count($libraryEntity->followers)]) }}</small>
						</span>
						<button class="btn xs" type="button" wire:click="editLibrary({{ $libraryEntity->id }})">{{ __('Wijzigen') }}</button>
					</span>
				@endif
			</div>
		@endforeach
		<a class="set" href="{{ route('rules', ['nieuw' => 1]) }}" style="color:var(--accent);text-decoration:none"><x-icon name="plus" small/>{{ __('Bibliotheek toevoegen') }}</a>
	</div>
</section>
