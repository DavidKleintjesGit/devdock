<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('skills') }}"><x-icon name="back" small/>{{ __('Skills') }}</a>
			<span class="eyebrow">{{ __('Skill') }}</span>
			<h1 class="mono" style="font-size:24px">{{ $skill }}</h1>
			<span class="lede">{{ __('Staat in :projects projecten, in :variants verschillende versies.', ['projects' => $rows->count(), 'variants' => $variants]) }} {{ $inLibrary ? __('De bibliotheek :library heeft hem.', ['library' => $inLibrary->name]) : __('De bibliotheek heeft hem nog niet.') }}</span>
		</div>
		@if ($inLibrary)
			<button class="btn" type="button" wire:click="openLibraryItem({{ $inLibrary->id }}, '{{ $skill }}')">{{ __('Inhoud en geschiedenis') }}</button>
		@endif
	</div>
</header>

<div class="pbody">
	<div class="stack">
		<section class="section">
			<div class="section-head"><h2>{{ __('Kies de versie voor de bibliotheek') }}</h2><span class="note">{{ __('Vergelijk eerst; alleen die ene gaat de bibliotheek in') }}</span></div>
			<div class="choices">
				@foreach ($rows as $row)
					<label class="choice" wire:key="sv-{{ $row['project'] }}">
						<input type="radio" name="skill-version" value="{{ $row['project'] }}" wire:model.live="versionFrom">
						<span class="radio"></span>
						<span class="grow"><b>{{ $row['project'] }}</b><small>{{ trans_choice(':count regel|:count regels', $row['lines'], ['count' => $row['lines']]) }} · {{ trans_choice(':count bestand|:count bestanden', $row['files'], ['count' => $row['files']]) }}{{ $loop->first ? ' · ' . __('de langste') : '' }}{{ $loop->last && ! $loop->first ? ' · ' . __('de kortste') : '' }}</small></span>
						<button class="btn xs" type="button" wire:click.prevent="open('{{ $row['project'] }}|{{ $skill }}')">{{ __('Bekijken') }}</button>
					</label>
				@endforeach
			</div>
		</section>

		<section class="section">
			<div class="section-head"><h2>{{ __('Daarna') }}</h2></div>
			<div class="card pad" style="display:flex;flex-direction:column;gap:8px;color:var(--fg-2);font-size:13.5px">
				<span>{{ __('De gekozen versie wordt een nieuwe versie van deze skill in de bibliotheek die je kiest. DevDock bewaart de hele map.') }}</span>
				<span>{{ __('Projecten die de bibliotheek volgen krijgen de stand "Gelijk" of "Zelf aangepast", net als bij Regels.') }}</span>
				<span>{{ __('Vanuit de bibliotheek trek je ze in één keer gelijk.') }}</span>
			</div>
		</section>
	</div>
</div>

<div style="position:sticky;bottom:0;z-index:15;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 44px;background:var(--surface-2);border-top:1px solid var(--line)">
	<span class="hint">{{ __('In bibliotheek') }}</span>
	<span class="seg" role="group" aria-label="{{ __('Bibliotheek') }}">
		@foreach ($libraries as $libraryEntity)
			<button type="button" aria-pressed="{{ $versionLibrary === $libraryEntity->id ? 'true' : 'false' }}" wire:click="$set('versionLibrary', {{ $libraryEntity->id }})">{{ $libraryEntity->name }}</button>
		@endforeach
	</span>
	<span class="spacer grow"></span>
	<button class="btn primary" type="button" wire:click="saveChosenVersion" @disabled($versionFrom === '' || $versionLibrary === null)>{{ $versionFrom === '' ? __('Kies eerst een versie') : __('Versie van :project opslaan', ['project' => $versionFrom]) }}</button>
</div>
