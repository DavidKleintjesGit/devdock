<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('rules') }}"><x-icon name="back" small/>{{ __('Regels') }}</a>
			<span class="eyebrow"><a href="#" wire:click.prevent="showLibrary({{ $library->id }})" style="text-decoration:none">{{ __('Bibliotheek :library', ['library' => $library->name]) }}</a></span>
			<h1 class="mono" style="font-size:24px">{{ $item->name }}</h1>
			<span class="lede">{{ $latest?->label() }} · {{ trans_choice(':count regel|:count regels', $latest?->lines() ?? 0, ['count' => $latest?->lines() ?? 0]) }} · {{ $latest?->createdAt?->translatedFormat('j F') }} · {{ trans_choice(':count project volgt :library|:count projecten volgen :library', count($library->followers), ['count' => count($library->followers), 'library' => $library->name]) }}</span>
		</div>
		<button class="btn" type="button" wire:click="openLibraryItem({{ $library->id }}, '{{ $item->name }}')">{{ __('Inhoud en geschiedenis') }}</button>
	</div>
</header>

<div class="pbody">
	<div class="stack">
		@if ($behind->isNotEmpty())
			<section class="section">
				<div class="section-head"><h2>{{ __('Loopt achter · :count', ['count' => $behind->count()]) }}</h2><span class="note">{{ __('Bijwerken is veilig: niemand heeft ze zelf veranderd') }}</span></div>
				<div class="list">
					@foreach ($behind as $standing)
						<div class="row" wire:key="behind-{{ $standing->project }}">
							<label class="tick cell"><input type="checkbox" @checked(in_array($standing->project, $pickedProjects, true)) wire:click="togglePicked('{{ $standing->project }}')"><span class="mark"></span></label>
							<span class="title grow"><b>{{ $standing->project }}</b><small>{{ __('op :version van :date', ['version' => $standing->onVersion?->label(), 'date' => $standing->onVersion?->createdAt?->translatedFormat('j F')]) }} · {{ trans_choice(':count regel|:count regels', $standing->lines ?? 0, ['count' => $standing->lines ?? 0]) }}</small></span>
							<button class="btn xs" type="button" wire:click="openFavorite('{{ $standing->project }}', '{{ $standing->path }}')">{{ __('Vergelijken') }}</button>
						</div>
					@endforeach
				</div>
			</section>
		@endif

		@if ($modified->isNotEmpty())
			<section class="section">
				<div class="section-head"><h2>{{ __('Zelf aangepast · :count', ['count' => $modified->count()]) }}</h2><span class="note">{{ __('Eerst vergelijken, of terugzetten als nieuwe versie') }}</span></div>
				<div class="list">
					@foreach ($modified as $standing)
						<div class="row" wire:key="mod-{{ $standing->project }}">
							<span class="title grow"><b>{{ $standing->project }}</b><small>{{ trans_choice(':count regel|:count regels', $standing->lines ?? 0, ['count' => $standing->lines ?? 0]) }} · {{ __('past bij geen enkele versie') }}</small></span>
							<button class="btn xs" type="button" wire:click="pushBack({{ $library->id }}, '{{ $item->name }}', '{{ $standing->project }}')">{{ __('Terugzetten naar de bibliotheek') }}</button>
						</div>
					@endforeach
				</div>
			</section>
		@endif

		@if ($missing->isNotEmpty())
			<section class="section">
				<div class="section-head"><h2>{{ __('Mist bij een volger · :count', ['count' => $missing->count()]) }}</h2></div>
				<div class="list">
					@foreach ($missing as $standing)
						<div class="row" wire:key="miss-{{ $standing->project }}">
							<label class="tick cell"><input type="checkbox" @checked(in_array($standing->project, $pickedProjects, true)) wire:click="togglePicked('{{ $standing->project }}')"><span class="mark"></span></label>
							<span class="title grow"><b>{{ $standing->project }}</b><small>{{ __('landt in :path', ['path' => $standing->path]) }}</small></span>
						</div>
					@endforeach
				</div>
			</section>
		@endif

		@if ($others !== [])
			<section class="section">
				<div class="section-head"><h2>{{ __('Zelfde naam, andere bibliotheek · :count', ['count' => count($others)]) }}</h2></div>
				<p class="hint" style="margin:0">{{ __('Deze projecten volgen een andere bibliotheek, met een eigen :name. Wordt hier niet bijgewerkt.', ['name' => $item->name]) }}</p>
				<div class="list">
					@foreach ($others as $other)
						<div class="row" wire:key="other-{{ $other['project'] }}">
							<span class="title grow"><b>{{ $other['project'] }}</b><small>{{ trans_choice(':count regel|:count regels', $other['lines'], ['count' => $other['lines']]) }} · {{ __('uit :library', ['library' => $other['library']]) }}</small></span>
						</div>
					@endforeach
				</div>
			</section>
		@endif

		@if ($equal->isNotEmpty())
			<section class="section">
				<div class="section-head"><h2>{{ __('Gelijk · :count', ['count' => $equal->count()]) }}</h2></div>
				<p class="hint" style="margin:0">{{ $equal->pluck('project')->implode(', ') }}</p>
			</section>
		@endif

		@if ($notFollowing !== [])
			<section class="section">
				<div class="section-head"><h2>{{ __('Volgt :library niet · :count', ['library' => $library->name, 'count' => count($notFollowing)]) }}</h2></div>
				<p class="hint" style="margin:0">{{ __('Toevoegen zet alleen dit bestand neer. De hele bibliotheek volgen kies je bij het project.') }}</p>
				<div class="list">
					@foreach (array_slice($notFollowing, 0, 12) as $candidate)
						<div class="row" wire:key="nf-{{ $candidate['project'] }}">
							<label class="tick cell"><input type="checkbox" @checked(in_array($candidate['project'], $pickedProjects, true)) wire:click="togglePicked('{{ $candidate['project'] }}')"><span class="mark"></span></label>
							<span class="title grow"><b>{{ $candidate['project'] }}</b><small>{{ __('landt in') }} <span class="mono">{{ $candidate['path'] }}</span></small></span>
						</div>
					@endforeach
				</div>
			</section>
		@endif
	</div>
</div>

<div style="position:sticky;bottom:0;z-index:15;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 44px;background:var(--surface-2);border-top:1px solid var(--line)">
	<span class="hint grow">{{ $missing->isEmpty() ? __('Geen volger mist hem.') : trans_choice(':count volger mist hem.|:count volgers missen hem.', $missing->count(), ['count' => $missing->count()]) }} {{ trans_choice(':count project gekozen.|:count projecten gekozen.', count($pickedProjects), ['count' => count($pickedProjects)]) }}</span>
	@if ($behind->isNotEmpty() && $pickedProjects === [])
		<button class="btn" type="button" wire:click="pickBehind">{{ __('Alle achterlopers kiezen') }}</button>
	@endif
	<button class="btn primary" type="button" wire:click="rollOutPicked" @disabled($pickedProjects === [])>{{ trans_choice('Bijwerken in :count project|Bijwerken in :count projecten', count($pickedProjects), ['count' => count($pickedProjects)]) }}</button>
</div>
