<div class="dialog-wrap" wire:click.self="closeLens">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="lens-new-title">
		<div class="dtop">
			<h2 id="lens-new-title">{{ $project ? __('Eigen lens toevoegen') : __('Lens toevoegen') }}</h2>
			<button class="icon-btn" type="button" wire:click="closeLens" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ $project ? __('Deze lens bestaat alleen in :project. Andere projecten zien hem niet.', ['project' => $project]) : __('Een lens van de standaard. Elk project krijgt hem en kijkt of zijn paden er bestaan.') }}</p>

			<div class="field">
				<label for="lens-name">{{ __('Naam') }}</label>
				<input id="lens-name" @class(['input', 'mono', 'invalid' => $errors->has('lensName')]) type="text" wire:model="lensName" placeholder="stack" autocomplete="off">
				@error('lensName')<span class="err">{{ $message }}</span>@enderror
			</div>

			<div class="field">
				<label for="lens-focus">{{ __('Waar hij op let') }}</label>
				<span class="help">{{ __('Dit krijgt de lens als opdracht, naast de regels die hij leest.') }}</span>
				<textarea id="lens-focus" @class(['textarea', 'invalid' => $errors->has('lensDescription')]) rows="3" wire:model="lensDescription"></textarea>
				@error('lensDescription')<span class="err">{{ $message }}</span>@enderror
			</div>

			<div class="field">
				<span class="label-text">{{ __('Bekijkt') }}</span>
				<div class="chips">
					@foreach ($lensPaths as $index => $path)<span class="tchip on mono">{{ $path }}<button class="icon-btn" type="button" style="width:18px;height:18px" wire:click="removePath({{ $index }})" aria-label="{{ __('Weghalen') }}"><x-icon name="x" small/></button></span>@endforeach
				</div>
				<div style="display:flex;gap:8px">
					<input class="input mono" type="text" wire:model.live.debounce.400ms="newPath" wire:keydown.enter="addPath" placeholder="resources/views/**" style="height:34px">
					<button class="btn sm" type="button" wire:click="addPath">{{ __('Toevoegen') }}</button>
				</div>
				@if ($newPathCount !== null)<span class="help">{{ trans_choice(':count bestand valt eronder|:count bestanden vallen eronder', $newPathCount, ['count' => $newPathCount]) }}</span>@endif
			</div>

			<div class="field">
				<span class="label-text">{{ __('Leest') }}</span>
				@if ($project && $ruleFiles !== [])
					<div class="list">
						@foreach ($ruleFiles as $file)
							<label class="tick row" style="min-height:40px" wire:key="new-rule-{{ $file }}">
								<input type="checkbox" @checked(in_array($file, $lensRules, true)) wire:click="toggleRule('{{ $file }}')">
								<span class="mark"></span>
								<span class="mono">{{ $file }}</span>
							</label>
						@endforeach
					</div>
				@else
					<span class="help">{{ __('Geen regelbestanden gevonden; de lens leest dan CLAUDE.md of AGENTS.md.') }}</span>
				@endif
			</div>

			<div class="field">
				<label for="lens-new-command">{{ __('Draait') }}</label>
				<span class="help">{{ __('Leeg laten: dan leest hij alleen de gewijzigde regels.') }}</span>
				<input id="lens-new-command" class="input mono" type="text" wire:model="lensCommand" placeholder="{{ __('geen commando') }}">
			</div>

			@if ($project)
				<div class="switchrow">
					<span class="txt"><b>{{ __('Ook in de standaard zetten') }}</b><small>{{ __('Andere projecten kunnen hem dan aanzetten. Waar hij op let gaat mee, de paden en regelbestanden van dit project niet.') }}</small></span>
					<button class="switch" type="button" role="switch" aria-checked="{{ $alsoInStandard ? 'true' : 'false' }}" wire:click="$toggle('alsoInStandard')" aria-label="{{ __('Ook in de standaard zetten') }}"></button>
				</div>
			@endif
		</div>
		<div class="dfoot">
			<span class="hint"></span>
			<button class="btn text" type="button" wire:click="closeLens">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="createLens">{{ __('Lens toevoegen') }}</button>
		</div>
	</div>
</div>
