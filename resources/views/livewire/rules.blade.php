<div class="page{{ $file ? ' drawer-open' : '' }}">
	<header class="pagebar">
		<div class="logo m-logo" aria-hidden="true">D</div>
		<h1>{{ __('Regels') }}</h1>
		<span class="count">{{ __(':files bestanden · :lines regels · :words woorden', ['files' => $total, 'lines' => number_format($lines, 0, ',', '.'), 'words' => number_format($words, 0, ',', '.')]) }}</span>
		<x-page-actions/>
	</header>

	<div class="toolbar">
		<div class="tb-search">
			<label class="search">
				<x-icon name="search"/><span class="sr">{{ __('Zoeken') }}</span>
				<input type="search" wire:model.live.debounce.300ms="query" placeholder="{{ __('Zoek op bestand of project') }}" autocomplete="off">
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

			<div class="seg" role="group" aria-label="{{ __('Herkomst') }}">
				<button type="button" aria-pressed="{{ $origin === '' ? 'true' : 'false' }}" wire:click="$set('origin', '')">{{ __('Alles') }} <span class="n">{{ $total }}</span></button>
				@foreach ($origins as $option)
					<button type="button" aria-pressed="{{ $origin === $option['case']->value ? 'true' : 'false' }}" wire:click="toggleOrigin('{{ $option['case']->value }}')">{{ $option['case']->label() }} <span class="n">{{ $option['count'] }}</span></button>
				@endforeach
			</div>
		</div>
	</div>

	<div class="content">
		@include('partials.active-filters')
		@if ($behind->isNotEmpty())
			<div class="alert" role="status">
				<x-icon name="alert"/>
				<span class="txt">
					<b style="color:var(--red)">{{ __(':count van de :total bestanden lopen achter op mtvtd-core', ['count' => $behind->count(), 'total' => $total]) }}</b>
					<span>{{ __(':names, in :projects projecten.', ['names' => $behindNames->implode(', '), 'projects' => $behindProjects]) }}</span>
				</span>
				<button class="btn sm" type="button" wire:click="askUpdateAll"><x-icon name="refresh" small/>{{ __('Alles bijwerken') }}</button>
			</div>
		@endif
		@include('partials.permissions', [
			'permissionGroups' => $permissionGroups,
			'permissionTotal' => $permissionTotal,
			'ignoredProjects' => $ignoredProjects,
		])

		@forelse ($groups as $group)
			<section class="section">
				<div class="section-head">
					<h2>{{ $group['case']->label() }}</h2>
					<span class="side-note">{{ trans_choice(':count bestand|:count bestanden', $group['files']->count(), ['count' => $group['files']->count()]) }}</span>
				</div>
				<div class="box list">
					<div class="listhead fcols">
						<span>{{ __('Bestand') }}</span>
						<span>{{ __('Herkomst') }}</span>
						<span class="r">{{ __('Aantal regels') }}</span>
						<span class="r">{{ __('Woorden') }}</span>
						<span></span>
					</div>
					@foreach ($group['files'] as $item)
						<div class="frow fcols{{ $openFileId === $item->ruleFileId->id ? ' open' : '' }}" wire:key="rule-{{ $item->ruleFileId->id }}">
							<button class="rowlink fname" type="button" wire:click="open({{ $item->ruleFileId->id }})">
								<x-icon name="file" small/>
								<span class="ftext">
									<span class="mono">{{ $item->path }}</span>
									@if ($item->summary)<small>{{ $item->summary }}</small>@endif
								</span>
								@if ($projects === [] || count($projects) > 1)<span class="muted mono">{{ $item->project }}</span>@endif
							</button>
							<span><span class="chip {{ $item->origin->tone() }}">{{ $item->origin->label() }}</span></span>
							<span class="lines mono">{{ $item->lines }}</span>
							<span class="read r mono">{{ number_format($item->words, 0, ',', '.') }}</span>
							<span class="chev"><x-icon name="chev" small/></span>
						</div>
					@endforeach
				</div>
			</section>
		@empty
			<div class="box">
				<div class="empty">
					<span class="bubble"><x-icon name="regels"/></span>
					<b>{{ __('Nog geen regels ingeladen') }}</b>
					<span>{{ __('Er zijn nog geen regelbestanden ingelezen.') }}</span>
					<button class="btn sm" type="button" wire:click="$refresh">{{ __('Nu ophalen') }}</button>
				</div>
			</div>
		@endforelse
	</div>

	@if ($confirming !== '')
		@include('partials.confirm-update')
	@endif

	@if ($file && $copying)
		@include('partials.copy-to-project', ['what' => $file->path, 'projects' => $projectsToCopyTo, 'warning' => $copyWarning])
	@endif

	@if ($file)
		<div class="scrim" wire:click="closeDrawer"></div>
		@include('partials.rule-drawer', ['file' => $file, 'tab' => $drawerTab, 'body' => $body, 'elsewhere' => $elsewhere])
	@endif
</div>
