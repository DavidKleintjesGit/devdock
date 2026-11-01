<div class="dialog-wrap" wire:click.self="$set('creating', false)">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="skill-new-title">
		<div class="dtop">
			<h2 id="skill-new-title">{{ __('Skill aanmaken') }}</h2>
			<button class="icon-btn" type="button" wire:click="$set('creating', false)" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>
		<div class="dbody">
			<p>{{ __('Dit legt een SKILL.md aan met de naam en de omschrijving erin. De inhoud schrijf je daarna in je editor.') }}</p>

			<div class="field">
				<label for="skill-name">{{ __('Naam') }}</label>
				<input id="skill-name" @class(['input', 'mono', 'invalid' => $errors->has('newName')]) type="text" wire:model="newName" placeholder="coverage-meting" autocomplete="off">
				@error('newName')<span class="err">{{ $message }}</span>@enderror
			</div>

			<div class="field">
				<label for="skill-where">{{ __('Waar') }}</label>
				<select id="skill-where" class="input" wire:model="newWhere">
					@foreach ($places as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
				</select>
			</div>

			<div class="field">
				<label for="skill-description">{{ __('Omschrijving') }}</label>
				<textarea id="skill-description" @class(['textarea', 'invalid' => $errors->has('newDescription')]) rows="4" wire:model="newDescription" placeholder="{{ __('Wanneer moet Claude deze skill pakken?') }}"></textarea>
				@error('newDescription')<span class="err">{{ $message }}</span>@enderror
			</div>
		</div>
		<div class="dfoot">
			<span class="hint"></span>
			<button class="btn text" type="button" wire:click="$set('creating', false)">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="create">{{ __('Aanmaken') }}</button>
		</div>
	</div>
</div>
