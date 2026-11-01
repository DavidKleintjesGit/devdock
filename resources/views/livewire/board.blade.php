<div class="page fixed">
	<header class="head">
		<div class="head-inner">
			<div class="head-title">
				<h1>{{ __('Bord') }}</h1>
				<span class="context">{{ $summary }}</span>
			</div>

			<div class="toolbar">
				<label class="search" style="max-width:340px">
					<x-icon name="search" small/>
					<span class="sr">{{ __('Zoeken') }}</span>
					<input type="search" wire:model.live.debounce.300ms="query" placeholder="{{ __('Zoek op ticket, titel of branch') }}" autocomplete="off">
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

				<details class="menu" x-data="{ open: false }" :open="open" @click.outside="open = false">
					<summary class="pick" :aria-expanded="open" @click.prevent="open = ! open"><span class="k">{{ __('Kolommen') }}</span>{{ __(':shown van :total', ['shown' => $shownCount, 'total' => $allColumns->count()]) }}<x-icon name="down" small/></summary>
					<div class="menu-pop left">
						@foreach ($allColumns as $column)
							<label class="tick" wire:key="column-{{ $column['case']->value }}">
								<input type="checkbox" wire:click="toggleColumn('{{ $column['case']->value }}')" @checked($column['isShown'])>
								<span class="mark"></span>
								<span class="dot {{ $column['case']->tone() }}"></span>{{ $column['case']->label() }}<span class="n">{{ $column['total'] }}</span>
							</label>
						@endforeach
					</div>
				</details>

				<button class="fbtn" type="button" aria-pressed="{{ $showBacklog ? 'true' : 'false' }}" wire:click="toggleBacklog">
					<x-icon name="backlog" small/>{{ $showBacklog ? __('Backlog verbergen') : __('Backlog tonen') }} <span class="n">{{ $backlogCount }}</span>
				</button>

				<span class="vsep"></span>

				@foreach ($signals as $signal)
					<button class="fbtn" type="button" aria-pressed="{{ $attention === $signal['case']->value ? 'true' : 'false' }}" wire:click="toggleAttention('{{ $signal['case']->value }}')" @if ($signal['title']) title="{{ $signal['title'] }}" @endif>
						@if ($signal['case']->hasPriorityMark())
							<svg class="ic s prio" width="10" height="10" aria-hidden="true"><use href="#i-prio"/></svg>
						@else
							<span class="dot {{ $signal['case']->tone() }}"></span>
						@endif
						{{ $signal['case']->label() }} <span class="n">{{ $signal['count'] }}</span>
					</button>
				@endforeach

				<span class="spacer"></span>
				<button class="btn" type="button" style="height:38px;padding:0 12px" wire:click="startTodo"><svg class="ic s" style="color:var(--accent)" aria-hidden="true"><use href="#i-plus"/></svg>{{ __('To-do') }}</button>
			</div>
		</div>
	</header>

	<div class="board-scroll">
		@if ($columns->isNotEmpty())
			<div class="cols" style="--cols:{{ $columns->count() }}">
				@foreach ($columns as $column)
					<section class="col" aria-label="{{ $column['case']->label() }}" wire:key="col-{{ $column['case']->value }}">
						<header class="col-head">
							<span class="dot {{ $column['case']->tone() }}"></span>
							<h2>{{ $column['case']->label() }}</h2>
							@if ($column['yours'] > 0)<span class="yours">{{ __(':count jouw beurt', ['count' => $column['yours']]) }}</span>@endif
							<span class="count">{{ $column['total'] }}</span>
						</header>

						@forelse ($column['cards'] as $card)
							@include('partials.card', ['card' => $card])
						@empty
							<div class="col-empty">
								@if ($filtered)
									{{ __('Niets in deze kolom voor dit filter.') }}
								@elseif ($column['case']->offersBacklog() && ! $showBacklog)
									<b>{{ __('Niets in de sprint') }}</b>{{ $column['case']->emptyText() }}
									<button class="btn sm" type="button" wire:click="toggleBacklog">{{ __('Backlog tonen') }}</button>
								@else
									{{ $column['case']->emptyText() }}
								@endif
							</div>
						@endforelse

						@if ($column['hidden'] > 0)
							<button class="more" type="button" wire:click="$set('backlogExpanded', true)">{{ __('Nog :count in de backlog', ['count' => $column['hidden']]) }}</button>
						@endif
					</section>
				@endforeach
			</div>
		@else
			<div class="empty">
				<b>{{ __('Geen kolommen zichtbaar') }}</b>
				<span>{{ __('Zet bij Kolommen minstens één kolom aan.') }}</span>
				<button class="btn sm" type="button" wire:click="showAllColumns">{{ __('Alles tonen') }}</button>
			</div>
		@endif
	</div>

	@if ($addingTodo)
		@include('partials.todo-new')
	@endif

	@if ($drawer)
		<div class="scrim" wire:click="closeDrawer"></div>

		@if ($drawer['type'] === 'session')
			@include('partials.session-drawer', $drawer)
		@elseif ($drawer['type'] === 'finding')
			@include('partials.finding-drawer', $drawer)
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
