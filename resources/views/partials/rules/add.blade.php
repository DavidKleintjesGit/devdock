<div class="dialog-wrap" wire:click.self="$set('addingRules', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="add-rules-title">
		<div class="dtop">
			<h2 id="add-rules-title">{{ __('Regels toevoegen aan :project', ['project' => $project]) }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('addingRules', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			@if ($libraries->isNotEmpty())
				<div class="field">
					<span class="label-text">{{ __('Hele bibliotheken') }}</span>
					<span class="help">{{ __('Kies er een of meer. Het project gaat ze volgen en hoort het als er een nieuwe versie is.') }}</span>
					<div class="list">
						@foreach ($libraries as $libraryEntity)
							<label class="tick row" style="min-height:52px" wire:key="add-lib-{{ $libraryEntity->id }}">
								<input type="checkbox" @checked(in_array($libraryEntity->id, $followPicks, true)) wire:click="toggleFollowPick({{ $libraryEntity->id }})">
								<span class="mark"></span>
								<span class="title grow"><b>{{ $libraryEntity->name }}</b><small>{{ trans_choice(':count regel|:count regels', count($libraryEntity->rules()), ['count' => count($libraryEntity->rules())]) }}: {{ $libraryEntity->ruleNames() }}</small></span>
							</label>
						@endforeach
					</div>
				</div>
			@endif

			<div class="field">
				<span class="label-text">{{ __('Losse bestanden') }}</span>
				<span class="help">{{ __('Worden gekopieerd. Ze volgen niets, maar onthouden waar ze vandaan komen.') }}</span>
				@if ($favorites->isNotEmpty())
					<span class="eyebrow" style="margin-top:4px">{{ __('Favorieten') }}</span>
					<div class="list">
						@foreach ($favorites as $favorite)
							<label class="tick row" style="min-height:48px" wire:key="add-fav-{{ $favorite['key'] }}">
								<input type="checkbox" @checked(in_array($favorite['key'], $filePicks, true)) wire:click="toggleFilePick('{{ $favorite['key'] }}')">
								<span class="mark"></span>
								<span class="title grow"><b class="mono" style="font-weight:500;font-size:12.5px">{{ $favorite['path'] }}</b><small>{{ __('uit :project', ['project' => $favorite['project']]) }}{{ $favorite['lines'] ? ' · ' . trans_choice(':count regel|:count regels', $favorite['lines'], ['count' => $favorite['lines']]) : '' }}</small></span>
							</label>
						@endforeach
					</div>
				@endif
				<label class="search" style="max-width:none;flex:none;margin-top:6px">
					<x-icon name="search" small/>
					<span class="sr">{{ __('Zoek een bestand in een ander project') }}</span>
					<input type="search" wire:model.live.debounce.300ms="otherSearch" placeholder="{{ __('Of zoek een bestand in een ander project') }}" autocomplete="off">
				</label>
				@if ($found->isNotEmpty())
					<div class="list">
						@foreach ($found as $hit)
							<label class="tick row" style="min-height:48px" wire:key="add-hit-{{ $hit['key'] }}">
								<input type="checkbox" @checked(in_array($hit['key'], $filePicks, true)) wire:click="toggleFilePick('{{ $hit['key'] }}')">
								<span class="mark"></span>
								<span class="title grow"><b class="mono" style="font-weight:500;font-size:12.5px">{{ $hit['path'] }}</b><small>{{ __('uit :project', ['project' => $hit['project']]) }} · {{ trans_choice(':count regel|:count regels', $hit['lines'], ['count' => $hit['lines']]) }}</small></span>
							</label>
						@endforeach
					</div>
				@endif
				<button class="btn text xs" type="button" style="align-self:flex-start;padding:0" wire:click="startRule">{{ __('Of begin met een nieuw, leeg bestand') }}</button>
			</div>
		</div>
		<div class="dfoot">
			<span class="hint">{{ trans_choice(':count bestand, in :place|:count bestanden, in :place', $fileCount, ['count' => $fileCount, 'place' => $landing->label()]) }} · <a href="#" wire:click.prevent="startLanding">{{ __('andere plek') }}</a></span>
			<button class="btn text" type="button" wire:click="$set('addingRules', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="addRules" @disabled($fileCount === 0)>{{ __('Toevoegen') }}</button>
		</div>
	</div>
</div>
