<div class="page">
	<header class="head">
		<div class="head-inner">
			<div class="head-title">
				<h1>{{ __('Sessies') }}</h1>
				<span class="context">{{ $summary }}</span>
			</div>

			<div style="display:flex;flex-direction:column;gap:10px">
				<div class="toolbar">
					<label class="search">
						<x-icon name="search" small/>
						<span class="sr">{{ __('Zoeken') }}</span>
						<input type="search" wire:model.live.debounce.300ms="query" placeholder="{{ __('Zoek op naam, project, branch of ticket') }}" autocomplete="off">
					</label>

					<x-project-picker
						:options="$projectOptions"
						:selected="$projects"
						:total="$projectTotal"
						:query="$projectQuery"
						query-property="projectQuery"
						toggle="toggleProject"
						clear="clearProjects"
					/>

					<button class="pick" type="button" aria-expanded="{{ $traitsOpen ? 'true' : 'false' }}" wire:click="$toggle('traitsOpen')">
						<x-icon name="filter" small/>{{ __('Kenmerken') }}
						@if ($activeTraits !== [])<span class="badge-n">{{ count($activeTraits) }}</span>@endif
						<x-icon :name="$traitsOpen ? 'up' : 'down'" small/>
					</button>

					<span class="spacer"></span>
					<span class="result">{{ $result }}</span>
					@if ($filtered)
						<button class="btn text" type="button" style="height:38px" wire:click="resetFilters">{{ __('Alles wissen') }}</button>
					@endif
				</div>

				@if (! $traitsOpen && $activeTraits !== [])
					<div class="chips" style="align-items:center">
						<span class="hint" style="margin-right:4px">{{ __('Aan:') }}</span>
						@foreach ($activeTraits as $trait)
							<button class="tchip on" type="button" wire:click="toggleTrait('{{ $trait->value }}')" aria-label="{{ __(':trait uitzetten', ['trait' => $trait->label()]) }}">{{ $trait->label() }}<x-icon name="x" small/></button>
						@endforeach
					</div>
				@endif
			</div>
		</div>
	</header>

	@if ($traitsOpen)
		<div class="tray">
			@foreach ($traitGroups as $group => $traits)
				<div class="tray-group">
					<h2 class="eyebrow">{{ $group }}</h2>
					<div class="chips">
						@foreach ($traits as $trait)
							<button @class(['tchip', 'keep' => $trait['case']->protects()]) type="button" aria-pressed="{{ $trait['isOn'] ? 'true' : 'false' }}" wire:click="toggleTrait('{{ $trait['case']->value }}')" title="{{ $trait['case']->explanation() }}">{{ $trait['case']->label() }} <span class="n">{{ $trait['count'] }}</span></button>
						@endforeach
					</div>
				</div>
			@endforeach
		</div>
	@endif

	<main class="main">
		@if ($rows->isEmpty())
			<div class="empty">
				<b>{{ $activeTraits !== [] ? trans_choice('Geen sessie past bij dit kenmerk|Geen sessie past bij alle :count', count($activeTraits), ['count' => count($activeTraits)]) : __('Geen sessies gevonden') }}</b>
				<span>{{ $activeTraits !== [] ? __('Kenmerken stapelen: een sessie moet ze allemaal hebben. Zet er een uit om meer te zien.') : __('Niets past bij je zoekopdracht.') }}</span>
				<button class="btn sm" type="button" wire:click="resetFilters">{{ $activeTraits !== [] ? __('Kenmerken wissen') : __('Alles wissen') }}</button>
			</div>
		@else
			<div class="list">
				<div class="list-head">
					<label class="tick">
						<input type="checkbox" wire:click="toggleAllShown" @checked($selectableCount > 0 && count($selected) >= $selectableCount)>
						<span class="mark"></span>
						<b>{{ __('Alles kiezen (:count)', ['count' => $selectableCount]) }}</b>
					</label>
					@if ($protectedCount > 0)
						<span class="hint">{{ trans_choice(':count is beschermd en blijft staan|:count zijn beschermd en blijven staan', $protectedCount, ['count' => $protectedCount]) }}</span>
					@endif
				</div>

				@foreach ($rows as $row)
					<div class="row" style="gap:14px;padding:0 18px;{{ $row['isSelected'] ? 'background:var(--accent-wash)' : '' }}" wire:key="session-{{ $row['session']->uuid }}">
						@if ($row['isProtected'])
							<span class="tick cell" title="{{ __('Beschermd') }}"><x-icon name="lock" small/></span>
						@else
							<label class="tick cell">
								<input type="checkbox" @checked($row['isSelected']) wire:click="toggleSelected('{{ $row['session']->uuid }}')" aria-label="{{ __(':name kiezen', ['name' => $row['session']->name()]) }}">
								<span class="mark"></span>
							</label>
						@endif
						<button class="row grow session-row" type="button" wire:click="open('{{ $row['session']->uuid }}')">
							<span class="sdot {{ $row['canGo'] ? 'red' : 'quiet' }}"></span>
							<span class="title session-name">
								<b class="ellipsis">{{ $row['session']->name() }}</b>
								<small class="mono ellipsis">{{ $row['session']->project ?? __('geen project') }}{{ $row['session']->branch ? ' · ' . $row['session']->branch : '' }}</small>
							</span>
							<span class="chips session-traits">
								@foreach ($row['traits'] as $trait)<span class="label">{{ $trait->label() }}</span>@endforeach
								@if ($row['moreTraits'] > 0)<span class="label muted">+{{ $row['moreTraits'] }}</span>@endif
							</span>
							<span class="session-stats">
								<span>{{ trans_choice(':count bericht|:count berichten', $row['session']->promptCount, ['count' => $row['session']->promptCount]) }}</span>
								<span class="mono" style="font-size:12px">{{ $row['session']->sizeLabel() }}</span>
								<span>{{ $row['age'] }}</span>
							</span>
						</button>
					</div>
				@endforeach
			</div>
		@endif
	</main>

	@if ($selected !== [])
		<div class="selection-bar">
			<button class="btn danger" type="button" wire:click="deleteSelected">{{ $confirmingDelete ? __('Zeker weten? Verwijderen') : __('Verwijderen') }}</button>
			<button class="btn text" type="button" style="color:var(--fg-2)" wire:click="clearSelection">{{ __('Selectie wissen') }}</button>
			<span class="hint" style="font-size:13px">{{ $pickedLine }}</span>
		</div>
	@endif

	@if ($drawer)
		<div class="scrim" wire:click="closeDrawer"></div>
		@include('partials.session-drawer', $drawer)

		@if ($renaming)
			@include('partials.session-rename', ['session' => $drawer['session']])
		@endif
	@endif
</div>
