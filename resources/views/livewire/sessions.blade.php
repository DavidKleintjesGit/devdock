<div class="page{{ $session ? ' drawer-open' : '' }}">
	<header class="pagebar">
		<div class="logo m-logo" aria-hidden="true">D</div>
		<h1>{{ __('Sessies') }}</h1>
		<span class="count">{{ __(':count sessies · :disk MB op schijf', ['count' => $all->count(), 'disk' => $diskTotal]) }}</span>
		<x-page-actions/>
	</header>

	<div class="toolbar">
		<div class="tb-search">
			<label class="search">
				<x-icon name="search"/><span class="sr">{{ __('Zoeken') }}</span>
				<input type="search" wire:model.live.debounce.300ms="query" placeholder="{{ __('Zoek op sessie of branch') }}" autocomplete="off">
			</label>
		</div>
		<div class="tb-filters">
			<x-project-picker
				:options="$projectOptions"
				:selected="$projects"
				:total="$projectTotal"
				:query="$projectQuery"
				query-property="projectQuery"
				toggle="toggleProject"
				clear="clearProjects"
			/>

			<div class="seg" role="group" aria-label="{{ __('Wanneer') }}">
				<button type="button" aria-pressed="{{ $when === '' ? 'true' : 'false' }}" wire:click="$set('when', '')">{{ __('Alles') }} <span class="n">{{ $all->count() }}</span></button>
				@foreach ($whenOptions as $option)
					<button type="button" aria-pressed="{{ $when === $option['case']->value ? 'true' : 'false' }}" wire:click="toggleWhen('{{ $option['case']->value }}')">{{ $option['case']->label() }} <span class="n">{{ $option['count'] }}</span></button>
				@endforeach
			</div>

			@foreach ($cleanupOptions as $option)
				<button class="btn" type="button" aria-pressed="{{ $cleanup === $option['case']->value ? 'true' : 'false' }}" wire:click="toggleCleanup('{{ $option['case']->value }}')">
					<span class="dot {{ $option['case']->tone() }}"></span>{{ $option['case']->label() }}<span class="n">{{ $option['count'] }}</span>
				</button>
			@endforeach
		</div>
	</div>

	<div class="content">
		@include('partials.active-filters')
		@if ($selected)
			<div class="bulk" role="region" aria-label="{{ __('Selectie') }}">
				<b>{{ __(':count geselecteerd', ['count' => count($selected)]) }}</b>
				<span style="flex:1"></span>
				<button class="btn sm" type="button" wire:click="clearSelection">{{ __('Selectie wissen') }}</button>
				<button class="btn sm danger solid" type="button" wire:click="deleteSelected">{{ $confirmingDelete ? __('Weet je het zeker?') : __('Verwijderen') }}</button>
			</div>
		@endif

		<div class="box list">
			@if ($shown->isNotEmpty())
				<label class="selectbar">
					<input class="check" type="checkbox" wire:click="toggleAllShown" @checked($shown->pluck('uuid')->diff($selected)->isEmpty())>
					{{ __('Alles selecteren') }}<span class="muted">{{ __(':count in beeld', ['count' => $shown->count()]) }}</span>
				</label>
				<div class="listhead scols">
					<span></span>
					<span>{{ __('Sessie') }}</span>
					<span class="c-status">{{ __('Status') }}</span>
					<span class="c-proj">{{ __('Project') }}</span>
					<span class="c-branch">{{ __('Branch') }}</span>
					<span class="r">{{ __('Prompts') }}</span>
					<span class="r">{{ __('Omvang') }}</span>
					<span class="r c-last">{{ __('Laatst actief') }}</span>
					<span></span>
				</div>
				@foreach ($groups as $group)
					<div class="group">
						<h2>{{ $group['case']->label() }}</h2>
						<span class="n">{{ __(':count sessies', ['count' => $filtered ? $group['sessions']->count() : $group['total']]) }}</span>
					</div>
					@foreach ($group['sessions'] as $row)
						@include('partials.session-row', ['row' => $row])
					@endforeach
				@endforeach
			@else
				<div class="empty">
					<span class="bubble"><x-icon name="sessies"/></span>
					<b>{{ $filtered ? __('Geen sessies gevonden') : __('Nog geen sessies') }}</b>
					@if ($filtered)
						<span>{{ __('Er zijn geen sessies die bij dit filter passen.') }}</span>
						<button class="btn sm" type="button" wire:click="resetFilters">{{ __('Filters wissen') }}</button>
					@endif
				</div>
			@endif
		</div>
	</div>

	@if ($session && $renaming)
		@include('partials.session-rename', ['session' => $session])
	@endif

	@if ($session)
		<div class="scrim" wire:click="closeDrawer"></div>
		@include('partials.session-drawer', ['session' => $session, 'tab' => $drawerTab, 'transcript' => $transcript, 'previous' => null, 'projectPath' => $projectPath])
	@endif
</div>
