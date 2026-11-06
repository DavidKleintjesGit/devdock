<div class="scrim" wire:click="closeLibraryItem"></div>
<aside class="drawer wide panel-drawer" role="dialog" aria-labelledby="item-title">
	<div class="dbar">
		<span class="kind">{{ $kindLabel }} · {{ $library->name }}</span>
		<button class="icon-btn" type="button" wire:click="closeLibraryItem" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<span class="meta"><span class="label teal">{{ $latest?->label() }} · {{ __('nieuwste') }}</span><span class="hint">{{ $latest?->createdAt?->toDisplayZone()->translatedFormat('j F') }} · {{ $latest?->source->label($library->name, $latest?->project) }}</span></span>
		<h2 id="item-title" class="mono" style="font-size:18px">{{ $item->name }}</h2>
		<span class="sub">{{ trans_choice(':count regel|:count regels', $latest?->lines() ?? 0, ['count' => $latest?->lines() ?? 0]) }} · {{ trans_choice(':count project volgt :library|:count projecten volgen :library', count($library->followers), ['count' => count($library->followers), 'library' => $library->name]) }}</span>
	</div>

	<nav class="tabs" role="tablist" style="margin-top:10px">
		<button type="button" role="tab" aria-selected="{{ $libraryTab === 'Inhoud' ? 'true' : 'false' }}" wire:click="$set('libraryTab', 'Inhoud')">{{ __('Inhoud') }}</button>
		<button type="button" role="tab" aria-selected="{{ $libraryTab === 'Geschiedenis' ? 'true' : 'false' }}" wire:click="$set('libraryTab', 'Geschiedenis')">{{ __('Geschiedenis') }}<span class="n">{{ count($versions) }}</span></button>
		<button type="button" role="tab" aria-selected="{{ $libraryTab === 'Projecten' ? 'true' : 'false' }}" wire:click="$set('libraryTab', 'Projecten')">{{ __('Projecten') }}<span class="n">{{ $standings->count() }}</span></button>
	</nav>

	<div class="dbody">
		@if ($libraryTab === 'Geschiedenis')
			<section class="dsec">
				<div class="list">
					@foreach ($versions as $version)
						<div class="row" style="align-items:flex-start;padding:14px 16px" wire:key="v-{{ $version->id }}">
							<label class="tick" style="margin-top:2px"><input type="checkbox" @checked(in_array($version->number, $pickedVersions, true)) wire:click="togglePickedVersion({{ $version->number }})" aria-label="{{ __(':version kiezen', ['version' => $version->label()]) }}"><span class="mark"></span></label>
							<span class="grow" style="display:flex;flex-direction:column;gap:3px">
								<span style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap"><b>{{ $version->label() }}</b><span class="hint">{{ $version->createdAt?->toDisplayZone()->translatedFormat('j F Y') }} · {{ $version->source->label($library->name, $version->project) }}</span></span>
								@if ($version->note)<span style="color:var(--fg-2)">{{ $version->note }}</span>@endif
								<span class="hint">{{ trans_choice(':count regel|:count regels', $version->lines(), ['count' => $version->lines()]) }}@if (isset($onVersion[$version->number])) · {{ trans_choice(':count project staat hierop: :projects|:count projecten staan hierop: :projects', count($onVersion[$version->number]), ['count' => count($onVersion[$version->number]), 'projects' => implode(', ', array_slice($onVersion[$version->number], 0, 4))]) }}@endif</span>
							</span>
							@if ($version->number !== $latest?->number)
								<button class="btn xs" type="button" wire:click="restoreVersion({{ $version->number }})" title="{{ __('Terughalen maakt van deze inhoud een nieuwe versie. De geschiedenis blijft staan.') }}">{{ __('Terughalen') }}</button>
							@endif
						</div>
					@endforeach
				</div>
				@if ($rollouts !== [])
					<h3 class="eyebrow" style="margin-top:10px">{{ __('Uitgerold') }}</h3>
					<div class="list">
						@foreach (array_slice($rollouts, 0, 8) as $rollout)
							<div class="row hint" style="min-height:40px">{{ $rollout['at']?->toDisplayZone()->translatedFormat('j F H:i') }} · v{{ $rollout['version'] }} → {{ $rollout['project'] }} <span class="mono">{{ $rollout['path'] }}</span></div>
						@endforeach
					</div>
				@endif
				@if ($versionDiff)
					<h3 class="eyebrow" style="margin-top:10px">{{ __(':versions vergeleken', ['versions' => $pickedLabel]) }}</h3>
					<x-diff :hunks="$versionDiff"/>
				@else
					<p class="hint" style="margin:0">{{ __('Vink twee versies aan om ze te vergelijken.') }}</p>
				@endif
			</section>
		@elseif ($libraryTab === 'Projecten')
			<section class="dsec">
				<div class="list">
					@forelse ($standings as $standing)
						<div class="row" wire:key="sp-{{ $standing->project }}">
							<span class="title grow"><b>{{ $standing->project }}</b><small class="mono">{{ $standing->path }}</small></span>
							<span class="label {{ $standing->standing->tone() }}">{{ $standing->standing->label() }}</span>
						</div>
					@empty
						<div class="row hint">{{ __('Nog geen project volgt deze bibliotheek.') }}</div>
					@endforelse
				</div>
			</section>
		@else
			<section class="dsec">
				@if ($editing)
					<div class="alert"><x-icon name="info" small/><span>{{ trans_choice('Je bewerkt :version. Na opslaan loopt :count volger achter, tot je hem bijwerkt.|Je bewerkt :version. Na opslaan lopen alle :count volgers achter, tot je ze bijwerkt.', count($library->followers), ['version' => $latest?->label(), 'count' => count($library->followers)]) }}</span><button class="btn xs" type="button" wire:click="$set('editing', false)">{{ __('Stoppen met bewerken') }}</button></div>
					<label class="sr" for="edit-contents">{{ __('Inhoud van :name', ['name' => $item->name]) }}</label>
					<textarea id="edit-contents" class="textarea code" wire:model="editContents"></textarea>
				@else
					<pre class="code-block" style="white-space:pre-wrap">{{ $latest?->contents }}</pre>
				@endif
			</section>
		@endif
	</div>

	<div class="dfoot">
		@if ($editing && $libraryTab === 'Inhoud')
			<input class="input grow" type="text" wire:model="editNote" placeholder="{{ __('Wat is er veranderd') }}" aria-label="{{ __('Wat is er veranderd') }}">
			<button class="btn primary" type="button" wire:click="saveEdit">{{ __('Opslaan als v:number', ['number' => ($latest?->number ?? 0) + 1]) }}</button>
		@else
			<span class="hint">{{ __('Naar projecten en naar de map schrijf je altijd met de hand.') }}</span>
			<button class="btn" type="button" wire:click="startEdit">{{ __('Bewerken') }}</button>
			@if ($rollOutCandidates !== [])
				<button class="btn primary" type="button" wire:click="askRollOut(@js($rollOutCandidates))">{{ __('Bijwerken in projecten') }}</button>
			@endif
		@endif
	</div>
</aside>
