<div class="page{{ $drawer ? ' drawer-open' : '' }}">
	<header class="pagebar">
		<div class="logo m-logo" aria-hidden="true">D</div>
		<h1>{{ __('Bord') }}</h1>
		<span class="count">{{ trans_choice(':count kaart|:count kaarten', $visibleCount, ['count' => $visibleCount]) }}</span>
		<x-page-actions/>
	</header>

	<div class="toolbar">
		<div class="tb-search">
			<label class="search">
				<x-icon name="search"/><span class="sr">{{ __('Zoeken') }}</span>
				<input type="search" wire:model.live.debounce.300ms="query" placeholder="{{ __('Zoek op ticket, branch of PR') }}" autocomplete="off">
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

			<div class="seg" role="group" aria-label="{{ __('Rol') }}">
				<button type="button" aria-pressed="{{ $role === '' ? 'true' : 'false' }}" wire:click="$set('role', '')">{{ __('Alles') }} <span class="n">{{ $totalCount }}</span></button>
				<button type="button" aria-pressed="{{ $role === 'author' ? 'true' : 'false' }}" wire:click="toggleRole('author')">{{ __('Van mij') }} <span class="n">{{ $roles['author'] }}</span></button>
				<button type="button" aria-pressed="{{ $role === 'reviewer' ? 'true' : 'false' }}" wire:click="toggleRole('reviewer')">{{ __('Ter review') }} <span class="n">{{ $roles['reviewer'] }}</span></button>
			</div>

			<details class="menu desk">
				<summary><span class="k">{{ __('Kolommen') }}</span>{{ __(':done van :total', ['done' => $columns->count(), 'total' => $allColumns->count()]) }}<x-icon name="down" small/></summary>
				<div class="menu-pop">
					@foreach ($allColumns as $column)
						<label>
							<input class="check" type="checkbox" wire:click="toggleColumn('{{ $column['case']->value }}')" @checked(in_array($column['case']->value, $shownColumns, true))>
							<span class="dot {{ $column['case']->tone() }}"></span>{{ $column['case']->label() }}<span class="n">{{ $column['total'] }}</span>
						</label>
					@endforeach
				</div>
			</details>

			@foreach ($signals as $signal)
				<button class="btn" type="button" aria-pressed="{{ $attention === $signal['case']->value ? 'true' : 'false' }}" wire:click="toggleAttention('{{ $signal['case']->value }}')">
					<span class="dot {{ $signal['case']->tone() }}"></span>{{ $signal['case']->label() }}<span class="n">{{ $signal['count'] }}</span>
				</button>
			@endforeach

			<span class="spacer"></span>
			<button class="btn" type="button" wire:click="startTodo"><x-icon name="plus" small/>{{ __('To-do') }}</button>

			<div class="seg col-switch" role="group" aria-label="{{ __('Kolom') }}">
				@foreach ($columns as $column)
					<button type="button" aria-pressed="{{ $mobileColumn === $column['case']->value ? 'true' : 'false' }}" wire:click="$set('mobileColumn', '{{ $column['case']->value }}')">
						<span class="dot {{ $column['case']->tone() }}"></span>{{ $column['case']->label() }} <span class="n">{{ $column['total'] }}</span>
					</button>
				@endforeach
			</div>
		</div>
	</div>

	<div class="content board">
		@include('partials.active-filters')
		@if ($columns->isNotEmpty())
			<div class="cols" style="--cols:{{ $columns->count() }}">
				@foreach ($columns as $column)
					<section class="col{{ $mobileColumn === $column['case']->value ? ' current' : '' }}" aria-label="{{ $column['case']->label() }}" title="{{ $column['case']->waitsOn() }}">
						<header class="col-head">
							<span class="dot {{ $column['case']->tone() }}"></span>
							<h2>{{ $column['case']->label() }}</h2>
							<span class="count">{{ $column['total'] }}</span>
						</header>
						@forelse ($column['cards'] as $card)
							@include('partials.card', ['card' => $card])
						@empty
							<div class="box">
								<div class="empty">
									<span class="bubble"><x-icon name="inbox"/></span>
									<b>{{ __('Geen kaarten') }}</b>
									<span>{{ $filtered ? __('Niets in deze kolom voor dit filter.') : __('Deze kolom is leeg.') }}</span>
								</div>
							</div>
						@endforelse
					</section>
				@endforeach
			</div>
		@else
			<div class="box">
				<div class="empty">
					<span class="bubble"><x-icon name="bord"/></span>
					<b>{{ __('Geen kolommen zichtbaar') }}</b>
					<span>{{ __('Zet bij Kolommen minstens één kolom aan.') }}</span>
					<button class="btn" type="button" wire:click="showAllColumns">{{ __('Alles tonen') }}</button>
				</div>
			</div>
		@endif
	</div>

	@if ($addingTodo)
		@include('partials.todo-new')
	@endif

	@if ($drawer)
		<div class="scrim" wire:click="closeDrawer"></div>

		@if ($drawer['type'] === 'session')
			@include('partials.session-drawer', ['session' => $drawer['session'], 'tab' => $drawer['tab'], 'transcript' => $drawer['transcript'], 'previous' => $drawer['previous'], 'projectPath' => $drawer['projectPath']])
		@else
			@include('partials.card-drawer', $drawer)
		@endif
	@endif

	@if ($drawer && $drawer['type'] === 'card' && $drawer['openDiffFile'])
		@include('partials.diff-panel', $drawer)
	@endif

	@if ($drawer && $drawer['type'] === 'session' && $renaming)
		@include('partials.session-rename', ['session' => $drawer['session']])
	@endif
</div>
