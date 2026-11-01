<div class="dialog-wrap" wire:click.self="$set('addingLibrary', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="library-new-title">
		<div class="dtop">
			<h2 id="library-new-title">{{ __('Bibliotheek toevoegen') }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('addingLibrary', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('Een bibliotheek is een set regels en skills die DevDock bewaart, met al hun versies. Projecten die hem volgen, worden ermee vergeleken. Je kunt er zoveel hebben als je wilt.') }}</p>

			<div class="field">
				<label for="library-name">{{ __('Naam') }}</label>
				<input id="library-name" @class(['input', 'invalid' => $errors->has('newLibraryName')]) type="text" wire:model="newLibraryName" autocomplete="off">
				@error('newLibraryName')<span class="err">{{ $message }}</span>@enderror
			</div>

			<div class="field">
				<span class="label-text">{{ __('Beginnen met') }}</span>
				<div class="choices">
					<label class="choice">
						<input type="radio" name="library-start" value="folder" wire:model.live="newLibraryStart">
						<span class="radio"></span>
						<span class="grow" style="display:flex;flex-direction:column;gap:8px">
							<span><b>{{ __('De regels uit een map') }}</b></span>
							@if ($newLibraryStart === 'folder')
								<input class="input mono" type="text" wire:model.live.debounce.500ms="newLibraryFolder" placeholder="C:\Sites\project\.claude\rules">
								@if ($foundRules !== [])
									<small>{{ trans_choice(':count regel gevonden: :names. Ze worden v1 in DevDock.|:count regels gevonden: :names. Ze worden v1 in DevDock.', count($foundRules), ['count' => count($foundRules), 'names' => $foundRuleNames]) }}</small>
								@elseif ($newLibraryFolder !== '')
									<small style="color:var(--amber)">{{ __('Geen regelbestanden in deze map.') }}</small>
								@endif
							@endif
						</span>
					</label>
					<label class="choice">
						<input type="radio" name="library-start" value="empty" wire:model.live="newLibraryStart">
						<span class="radio"></span>
						<span><b>{{ __('Leeg') }}</b><small>{{ __('Je vult hem in DevDock, of vanuit een project met "Terugzetten naar de bibliotheek".') }}</small></span>
					</label>
				</div>
			</div>

			@if ($newLibraryStart === 'folder')
				<div class="switchrow">
					<span class="txt"><b>{{ __('Deze map gekoppeld houden') }}</b><small>{{ __('Verandert er iets in de map, dan komt dat binnen als nieuwe versie. Naar de map schrijven doe je altijd met de hand.') }}</small></span>
					<button class="switch" type="button" role="switch" aria-checked="{{ $keepLinked ? 'true' : 'false' }}" wire:click="$toggle('keepLinked')" aria-label="{{ __('Deze map gekoppeld houden') }}"></button>
				</div>

				<div class="field">
					<label for="library-skills">{{ __('Skills uit een map') }}</label>
					<span class="help">{{ __('Mag leeg blijven.') }}</span>
					<input id="library-skills" class="input mono" type="text" wire:model.live.debounce.500ms="newLibrarySkillsFolder" placeholder="C:\Sites\project\.claude\skills">
					@if ($foundSkills !== [])<span class="help">{{ trans_choice(':count skill gevonden.|:count skills gevonden.', count($foundSkills), ['count' => count($foundSkills)]) }}</span>@endif
				</div>

				@if ($matchingProjects !== [])
					<div class="field">
						<span class="label-text">{{ __('Wie volgt hem') }}</span>
						<span class="help">{{ __('DevDock vond projecten met dezelfde bestanden.') }}</span>
						<div class="list">
							@foreach ($matchingProjects as $matchingProject => $count)
								<label class="tick row" style="min-height:44px" wire:key="mp-{{ $matchingProject }}">
									<input type="checkbox" @checked(in_array($matchingProject, $newLibraryFollowers, true)) wire:click="toggleNewFollower('{{ $matchingProject }}')">
									<span class="mark"></span>
									<span class="title grow"><b>{{ $matchingProject }}</b><small>{{ trans_choice(':count bestand met dezelfde naam|:count bestanden met dezelfde naam', $count, ['count' => $count]) }}</small></span>
								</label>
							@endforeach
						</div>
					</div>
				@endif
			@endif
		</div>
		<div class="dfoot">
			<span class="hint">{{ __('De bestanden komen in DevDock; de map blijft zoals hij is') }}</span>
			<button class="btn text" type="button" wire:click="$set('addingLibrary', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="createLibrary">{{ __('Toevoegen') }}</button>
		</div>
	</div>
</div>
