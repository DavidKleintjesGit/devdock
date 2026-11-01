<div class="dialog-wrap" wire:click.self="$set('creatingRule', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="rule-new-title">
		<div class="dtop">
			<h2 id="rule-new-title">{{ __('Regel maken') }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('creatingRule', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('DevDock legt het bestand aan. De inhoud schrijf je daarna in je editor.') }}</p>

			<div class="field">
				<label for="rule-name">{{ __('Naam') }}</label>
				<input id="rule-name" @class(['input', 'mono', 'invalid' => $errors->has('newRuleName')]) type="text" wire:model.live.debounce.300ms="newRuleName" placeholder="livewire" autocomplete="off">
				@error('newRuleName')<span class="err">{{ $message }}</span>@enderror
			</div>

			<div class="field">
				<span class="label-text">{{ __('Waar') }}</span>
				<div class="choices">
					@if ($libraries->isNotEmpty())
						<div class="choice" style="flex-direction:column;gap:8px;cursor:default">
							<b>{{ __('In een bibliotheek') }}</b>
							<div class="choices row">
								@foreach ($libraries as $libraryEntity)
									<label class="choice compact" wire:key="where-{{ $libraryEntity->id }}">
										<input type="radio" name="rule-where" value="library:{{ $libraryEntity->id }}" wire:model.live="newRuleWhere">
										<span class="radio"></span><span>{{ $libraryEntity->name }}</span>
									</label>
								@endforeach
							</div>
							<small>{{ __('Daarna kies je in welke projecten hij komt.') }}</small>
						</div>
					@endif
					@if ($project)
						<label class="choice">
							<input type="radio" name="rule-where" value="project" wire:model.live="newRuleWhere">
							<span class="radio"></span>
							<span><b>{{ __('Alleen in :project', ['project' => $project]) }}</b><small>{{ __('Komt in de repo; collega\'s krijgen hem mee.') }}</small></span>
						</label>
					@endif
					<label class="choice">
						<input type="radio" name="rule-where" value="global" wire:model.live="newRuleWhere">
						<span class="radio"></span>
						<span><b>{{ __('Globaal') }}</b><small>{{ __('Alleen jij, in elk project.') }}</small></span>
					</label>
				</div>
			</div>

			<div class="field">
				<label for="rule-start">{{ __('Begin met') }}</label>
				<select id="rule-start" class="input" wire:model.live="newRuleStart">
					<option value="empty">{{ __('Een leeg bestand') }}</option>
					@foreach ($favorites as $favorite)
						<option value="copy:{{ $favorite['project'] }}|{{ $favorite['path'] }}">{{ __('Een kopie van :project / :path', ['project' => $favorite['project'], 'path' => $favorite['path']]) }}</option>
					@endforeach
				</select>
			</div>

			@if ($fileName !== '')
				<div class="alert teal"><x-icon name="info" small/><span>{{ $libraryName ? __('Wordt v1 van :name in bibliotheek :library.', ['name' => $fileName, 'library' => $libraryName]) : __('Wordt :name.', ['name' => $fileName]) }}</span></div>
			@endif
		</div>
		<div class="dfoot">
			<span class="hint">{{ $libraryName ? __('Naar de gekoppelde map schrijf je daarna met de hand') : '' }}</span>
			<button class="btn text" type="button" wire:click="$set('creatingRule', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="createRule">{{ __('Aanmaken') }}</button>
		</div>
	</div>
</div>
