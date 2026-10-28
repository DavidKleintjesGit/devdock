<div class="page{{ $skill || $creating ? ' drawer-open' : '' }}">
	<header class="pagebar">
		<div class="logo m-logo" aria-hidden="true">D</div>
		<h1>{{ __('Skills') }}</h1>
		<span class="count">{{ trans_choice(':count skill|:count skills', $total, ['count' => $total]) }}</span>
		<x-page-actions/>
	</header>

	<div class="toolbar">
		<div class="tb-search">
			<label class="search">
				<x-icon name="search"/><span class="sr">{{ __('Zoeken') }}</span>
				<input type="search" wire:model.live.debounce.300ms="query" placeholder="{{ __('Zoek op naam of omschrijving') }}" autocomplete="off">
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

			<div class="seg" role="group" aria-label="{{ __('Waar') }}">
				<button type="button" aria-pressed="{{ $scope === '' ? 'true' : 'false' }}" wire:click="$set('scope', '')">{{ __('Alles') }} <span class="n">{{ $total }}</span></button>
				@foreach ($scopes as $option)
					<button type="button" aria-pressed="{{ $scope === $option['case']->value ? 'true' : 'false' }}" wire:click="toggleScope('{{ $option['case']->value }}')">{{ $option['case']->label() }} <span class="n">{{ $option['count'] }}</span></button>
				@endforeach
			</div>
			<span class="spacer"></span>
			<button class="btn primary" type="button" wire:click="startCreating"><x-icon name="plus" small/>{{ __('Skill aanmaken') }}</button>
		</div>
	</div>

	<div class="content">
		@include('partials.active-filters')
		@foreach ($groups as $group)
			<section class="section">
				<div class="section-head">
					<h2>{{ $group['case']->label() }}</h2>
					<span class="side-note">{{ $group['case']->note() ? $group['case']->note() . ' · ' : '' }}{{ trans_choice(':count skill|:count skills', $group['skills']->count(), ['count' => $group['skills']->count()]) }}</span>
				</div>
				<div class="box list">
					@forelse ($group['skills'] as $item)
						<div class="krow{{ $openSkill === $item->name ? ' open' : '' }}" wire:key="skill-{{ $item->skillId->id }}">
							<button class="rowlink kname" type="button" wire:click="open('{{ $item->name }}')">
								<b>{{ $item->name }}</b><small>{{ $item->location() }}</small>
							</button>
							<span class="kdesc">{{ $item->description }}</span>
							<span class="acts above">
								@if ($item->isEditable())
									<span class="chip teal">{{ $item->scope->label() }}</span>
								@else
									<span class="chip gray"><x-icon name="lock" small/>{{ __('Alleen-lezen') }}</span>
								@endif
							</span>
							<span class="chev"><x-icon name="chev" small/></span>
						</div>
					@empty
						<div class="empty">
							<span class="bubble"><x-icon name="skills"/></span>
							<b>{{ __('Nog geen skills') }}</b>
							<span>{{ $group['case']->emptyHint() }}</span>
						</div>
					@endforelse
				</div>
			</section>
		@endforeach
	</div>

	@if ($skill || $creating)
		<div class="scrim" wire:click="closeDrawer"></div>
		@if ($skill && $copying)
			@include('partials.copy-to-project', ['what' => $skill->name, 'projects' => $projectsToCopyTo, 'warning' => $copyWarning])
		@endif

		@if ($creating)
			@include('partials.skill-new', ['projects' => $projects, 'globalScope' => $globalScope])
		@else
			@include('partials.skill-drawer', ['skill' => $skill, 'body' => $body])
		@endif
	@endif
</div>
