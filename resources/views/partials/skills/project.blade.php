<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('skills') }}"><x-icon name="back" small/>{{ __('Skills') }}</a>
			<span class="eyebrow">{{ __('Skills') }}</span>
			<h1>{{ $project }}</h1>
			<span class="lede">{{ __('Wat een agent hier kan pakken: :own uit het project, :global globaal en :plugins uit plugins.', ['own' => $ownCount, 'global' => $global->count(), 'plugins' => $plugins->count()]) }}</span>
		</div>
		<button class="btn primary" type="button" wire:click="startCreating"><x-icon name="plus" small/>{{ __('Skill maken') }}</button>
	</div>
</header>

<div class="pbody">
	<div class="stack">
		@if ($differCount > 0 && ! $libraryHasSkills)
			<div class="alert blue">
				<x-icon name="info" small/>
				<span class="grow"><b>{{ trans_choice(':count skill van dit project staat ook elders, in een andere versie|:count skills van dit project staan ook elders, in een andere versie', $differCount, ['count' => $differCount]) }}</b><br>{{ __('De bibliotheek voor skills is nog leeg. Zet de beste versie erin, dan kun je ze gelijk trekken.') }}</span>
				@if ($firstDiffering)<button class="btn sm" type="button" wire:click="showSkill('{{ $firstDiffering }}')">{{ __('Bekijken') }}</button>@endif
			</div>
		@endif

		<section class="section">
			<div class="section-head"><h2>{{ __('Uit dit project') }}</h2><span class="note">{{ trans_choice(':count skill · collega\'s krijgen hem mee|:count skills · collega\'s krijgen ze mee', $ownCount, ['count' => $ownCount]) }}</span></div>
			@if ($rows->isEmpty())
				<div class="empty"><b>{{ __('Dit project heeft geen eigen skills') }}</b><span>{{ __('Maak er een, of neem er een over uit een ander project.') }}</span><button class="btn sm" type="button" wire:click="startCreating">{{ __('Skill maken') }}</button></div>
			@else
				<div class="list">
					@foreach ($rows as $row)
						<div class="skillrow" wire:key="ps-{{ $row['skill']->name }}">
							<button class="skillrow-open" type="button" wire:click="open('{{ $project }}|{{ $row['skill']->name }}')">
								<b class="mono">{{ $row['skill']->name }}</b>
								<span class="ellipsis">{{ $row['skill']->description }}</span>
							</button>
							@if ($row['differs'])
								<button class="btn text xs" type="button" wire:click="showSkill('{{ $row['skill']->name }}')" title="{{ __('Versies') }}">{{ $row['note'] }}</button>
							@else
								<span class="hint">{{ $row['note'] }}</span>
							@endif
							<x-icon name="chev" small/>
						</div>
					@endforeach
					@if ($hiddenRows > 0)
						<button class="row" type="button" style="color:var(--accent);font-size:13px;min-height:44px" wire:click="$set('allProjectSkills', true)">{{ __('Nog :count skills', ['count' => $hiddenRows]) }}</button>
					@endif
				</div>
			@endif
		</section>

		<section class="section">
			<div class="section-head"><h2>{{ __('Globaal, geldt hier ook') }}</h2><span class="note">{{ __('alleen jij') }}</span></div>
			<div class="list">
				@forelse ($global as $skillEntity)
					<button class="row tall" type="button" wire:click="open('|{{ $skillEntity->name }}')">
						<span class="title" style="width:240px"><b class="mono" style="font-weight:500;font-size:13px">{{ $skillEntity->name }}</b></span>
						<span class="grow ellipsis" style="font-size:13px;color:var(--fg-2)">{{ $skillEntity->description }}</span>
						<span class="hint mono">~/.claude/skills</span>
					</button>
				@empty
					<div class="row hint">{{ __('Geen globale skills.') }}</div>
				@endforelse
			</div>
		</section>

		<section class="section">
			<div class="section-head"><h2>{{ __('Uit plugins, geldt hier ook') }}</h2><span class="note">{{ trans_choice(':count skill · alleen-lezen|:count skills · alleen-lezen', $plugins->count(), ['count' => $plugins->count()]) }}</span></div>
			<button class="btn sm" type="button" style="align-self:flex-start" wire:click="showSection('plugins')">{{ __('Bekijken') }}</button>
		</section>
	</div>
</div>
