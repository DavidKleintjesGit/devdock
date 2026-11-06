<div class="scrim" wire:click="closeDrawer"></div>
<aside class="drawer panel-drawer" role="dialog" aria-labelledby="skill-title">
	<div class="dbar">
		<span class="kind">{{ __('Skill') }} · {{ $skill->project ?? $skill->scope->label() }}</span>
		@if ($skill->isEditable())
			<details class="menu" x-data="{ open: false }" :open="open" @click.outside="open = false">
				<summary class="icon-btn filled" aria-label="{{ __('Acties') }}" @click.prevent="open = ! open"><x-icon name="meer"/></summary>
				<div class="menu-pop" role="menu">
					<button type="button" role="menuitem" wire:click="startCopy">{{ __('Overnemen in een project') }}</button>
					@if ($skill->project)
						@foreach ($libraries as $libraryEntity)
							<button type="button" role="menuitem" wire:click="intoLibrary({{ $libraryEntity->id }})">{{ __('In de bibliotheek :library zetten', ['library' => $libraryEntity->name]) }}</button>
						@endforeach
						<button type="button" role="menuitem" wire:click="toggleFavorite('{{ $skill->project }}', '{{ $skill->name }}')"><x-icon name="star" small/>{{ $isFavorite ? __('Geen favoriet meer') : __('Favoriet') }}</button>
						<button type="button" role="menuitem" wire:click="showSkill('{{ $skill->name }}')">{{ __('Versies in andere projecten') }}</button>
					@endif
				</div>
			</details>
		@endif
		<button class="icon-btn" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<span class="meta"><span class="label">{{ $skill->scope->label() }}</span><span class="hint">{{ trans_choice(':count regel|:count regels', $skill->lines, ['count' => $skill->lines]) }}</span></span>
		<h2 id="skill-title" class="mono" style="font-size:18px">{{ $skill->name }}</h2>
		@if ($skill->description)<p class="sub" style="margin:0">{{ $skill->description }}</p>@endif
	</div>

	<div class="dbody">
		<section class="dsec">
			<dl style="display:grid;grid-template-columns:100px 1fr;gap:8px 14px;font-size:13.5px">
				<dt class="muted">{{ __('Waar') }}</dt><dd style="margin:0">{{ $skill->project ?? $skill->scope->label() }}</dd>
				<dt class="muted">{{ __('Bestand') }}</dt><dd style="margin:0" class="mono">{{ $skill->location() }}</dd>
			</dl>
		</section>

		@if ($copying)
			<section class="dsec">
				<div class="card pad" style="display:flex;flex-direction:column;gap:10px">
					<b>{{ __('Overnemen in een project') }}</b>
					<select class="input" wire:model.live="copyTo" aria-label="{{ __('Waarheen') }}">
						<option value="">{{ __('Kies een project') }}</option>
						@foreach ($projectsToCopyTo as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
					</select>
					@if ($copyWarning)<span class="hint" style="color:var(--amber)">{{ $copyWarning }}</span>@endif
					<span class="hint">{{ __('Dit kopieert alleen; er wordt nooit iets weggehaald.') }}</span>
					<span style="display:flex;gap:8px;justify-content:flex-end">
						<button class="btn text sm" type="button" wire:click="$set('copying', false)">{{ __('Annuleren') }}</button>
						<button class="btn primary sm" type="button" wire:click="copy" @disabled($copyTo === '')>{{ __('Overnemen') }}</button>
					</span>
				</div>
			</section>
		@endif

		<section class="dsec">
			<h3 class="eyebrow">{{ __('Inhoud') }}</h3>
			<div class="md">{!! $body !!}</div>
		</section>
	</div>

	<div class="dfoot">
		<span class="hint">{{ trans_choice(':count regel · gelezen van schijf|:count regels · gelezen van schijf', $skill->lines, ['count' => $skill->lines]) }}</span>
		@if ($skill->isEditable())
			<button class="btn" type="button" wire:click="startCopy">{{ __('Overnemen') }}</button>
		@endif
	</div>
</aside>
