<aside class="drawer" role="dialog" aria-labelledby="drawer-title">
	<div class="grab" aria-hidden="true"></div>
	<div class="dbar">
		<span class="kind">{{ __('Skill') }}</span>
		<button class="btn icon ghost close" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>
	<div class="dhead">
		<h2 id="drawer-title">{{ __('Skill aanmaken') }}</h2>
		<p class="sub">{{ __('Dit legt een SKILL.md aan met de naam en de omschrijving erin. De inhoud schrijf je daarna in je editor.') }}</p>
	</div>
	<div class="dbody">
		<form wire:submit="create">
			<div class="field">
				<label class="eyebrow" for="skill-name">{{ __('Naam') }}</label>
				<input id="skill-name" class="input mono" type="text" wire:model="newName" placeholder="coverage-meting" autocomplete="off">
				@error('newName')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
			</div>

			<div class="field">
				<label class="eyebrow" for="skill-where">{{ __('Waar') }}</label>
				<select id="skill-where" class="input" wire:model="newWhere">
					<option value="{{ $globalScope }}">{{ __('Globaal, geldt in elk project') }}</option>
					@foreach ($projects as $name)
						<option value="{{ $name }}">{{ $name }}</option>
					@endforeach
				</select>
			</div>

			<div class="field">
				<label class="eyebrow" for="skill-description">{{ __('Omschrijving') }}</label>
				<textarea id="skill-description" class="input" rows="5" wire:model="newDescription" placeholder="{{ __('Wanneer moet Claude deze skill pakken?') }}"></textarea>
				@error('newDescription')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
			</div>

			<div style="display:flex;gap:8px;flex-wrap:wrap">
				<button class="btn primary" type="submit"><x-icon name="plus" small/>{{ __('Aanmaken') }}</button>
				<button class="btn" type="button" wire:click="closeDrawer">{{ __('Annuleren') }}</button>
			</div>
		</form>
	</div>
</aside>
