<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('rules') }}"><x-icon name="back" small/>{{ __('Regels') }}</a>
			<span class="eyebrow">{{ __('Regels') }}</span>
			<h1>{{ $project }}</h1>
			<span class="lede">{{ $readCount > 0 ? trans_choice('Wat een agent hier leest: :count bestand uit het project, en je globale toestemmingen.|Wat een agent hier leest: :count bestanden uit het project, en je globale toestemmingen.', $readCount, ['count' => $readCount]) : __('Wat een agent hier leest: alleen je globale toestemmingen.') }}</span>
		</div>
		<button class="btn primary" type="button" wire:click="startAdding"><x-icon name="plus" small/>{{ __('Regels toevoegen') }}</button>
	</div>
</header>

<div class="pbody">
	<div class="stack">
		@if ($followed->isEmpty() && $own->isEmpty() && $yours->isEmpty())
			<div class="empty">
				<b>{{ __('Dit project heeft nog geen regels') }}</b>
				<span>{{ __('Geen CLAUDE.md, geen map met regels, en het volgt geen bibliotheek. Een agent werkt hier dus zonder afspraken.') }}</span>
				<span style="display:flex;gap:8px;margin-top:6px">
					<button class="btn sm primary" type="button" wire:click="startAdding">{{ __('Regels toevoegen') }}</button>
					<button class="btn sm" type="button" wire:click="startRule">{{ __('Leeg bestand maken') }}</button>
				</span>
			</div>
		@else
			<div class="list">
				<div class="row" style="min-height:60px;padding:10px 18px">
					<span class="grow" style="display:flex;flex-direction:column;gap:6px">
						<span class="hint">{{ __('Volgt') }}</span>
						<span class="chips">
							@forelse ($followed as $libraryEntity)
								<span class="followchip">{{ $libraryEntity->name }}<small>{{ trans_choice(':count regel|:count regels', count($libraryEntity->rules()), ['count' => count($libraryEntity->rules())]) }}</small><button class="icon-btn" type="button" style="width:18px;height:18px;color:inherit" wire:click="follow({{ $libraryEntity->id }}, false)" aria-label="{{ __('Niet meer volgen') }}"><x-icon name="x" small/></button></span>
							@empty
								<span class="hint">{{ __('geen bibliotheek') }}</span>
							@endforelse
						</span>
					</span>
					@if ($unfollowed->isNotEmpty())
						<details class="menu" x-data="{ open: false }" :open="open" @click.outside="open = false">
							<summary class="btn sm" @click.prevent="open = ! open">{{ $followed->isEmpty() ? __('Een bibliotheek volgen') : __('Nog een bibliotheek volgen') }}</summary>
							<div class="menu-pop">
								@foreach ($unfollowed as $libraryEntity)
									<button type="button" wire:click="follow({{ $libraryEntity->id }}, true)">{{ $libraryEntity->name }}<span class="n">{{ trans_choice(':count regel|:count regels', count($libraryEntity->rules()), ['count' => count($libraryEntity->rules())]) }}</span></button>
								@endforeach
							</div>
						</details>
					@endif
				</div>
				<button class="row" type="button" style="min-height:60px;padding:0 18px" wire:click="startLanding">
					<span class="grow" style="display:flex;flex-direction:column"><span class="hint">{{ __('Regels landen in') }}</span><span class="mono" style="font-size:13px">{{ $landingLabel }}</span></span>
					<span class="hint" style="color:{{ $landingExists ? 'var(--green-dot)' : 'var(--muted)' }}">{{ $landingExists ? __('bestaat') : __('wordt aangemaakt') }}</span>
					<span class="btn sm">{{ __('Wijzigen') }}</span>
				</button>
			</div>

			@foreach ($groups as $group)
				<section class="section" wire:key="group-{{ $group['library']->id }}">
					<div class="section-head"><h2>{{ __('Uit :library', ['library' => $group['library']->name]) }}</h2><span class="note">{{ __(':present van de :total', ['present' => $group['present'], 'total' => $group['standings']->count()]) }}{{ $group['deviating'] ? ' · ' . trans_choice(':count wijkt af|:count wijken af', $group['deviating'], ['count' => $group['deviating']]) : '' }}</span></div>
					<div class="list">
						@foreach ($group['standings'] as $standing)
							<button @class(['frow', 'warm' => $standing->standing->deviates()]) type="button" wire:click="openFile('{{ $standing->path }}')" wire:key="st-{{ $standing->item->id }}" @disabled(! $standing->isPresent())>
								<span class="fname"><span class="mono">{{ $standing->item->name }}</span>@if ($standing->standing->deviates())<small>{{ $standing->detail() }}</small>@endif</span>
								<span><span class="label {{ $standing->standing->tone() }}">{{ $standing->standing->label() }}</span></span>
								<span class="lines">{{ $standing->lines }}</span>
								<x-icon name="chev" small/>
							</button>
						@endforeach
					</div>
				</section>
			@endforeach

			@if ($own->isNotEmpty())
				<section class="section">
					<div class="section-head"><h2>{{ __('Alleen in dit project') }}</h2><span class="note">{{ trans_choice(':count bestand · collega\'s krijgen het mee|:count bestanden · collega\'s krijgen ze mee', $own->count(), ['count' => $own->count()]) }}</span></div>
					<div class="list">
						@foreach ($own as $fileEntity)
							<button class="frow" type="button" wire:click="openFile('{{ $fileEntity->path }}')" wire:key="own-{{ $fileEntity->ruleFileId->id }}">
								<span class="fname"><span class="mono">{{ $fileEntity->path }}@if (in_array($fileEntity->path, $favoritePaths, true))<span class="star" title="{{ __('favoriet') }}"><x-icon name="star" small/></span>@endif</span>@if ($fileEntity->summary)<small>{{ $fileEntity->summary }}</small>@endif</span>
								<span class="hint">{{ $fileEntity->group->label() }}</span>
								<span class="lines">{{ $fileEntity->lines }}</span>
								<x-icon name="chev" small/>
							</button>
						@endforeach
					</div>
				</section>
			@endif

			@if ($yours->isNotEmpty())
				<section class="section">
					<div class="section-head"><h2>{{ __('Alleen van jou') }}</h2><span class="note">{{ trans_choice(':count bestand · niet in de repo, wordt nooit vergeleken of uitgerold|:count bestanden · niet in de repo, wordt nooit vergeleken of uitgerold', $yours->count(), ['count' => $yours->count()]) }}</span></div>
					<div class="list">
						@foreach ($yours as $fileEntity)
							<button class="frow" type="button" wire:click="openFile('{{ $fileEntity->path }}')" wire:key="yours-{{ $fileEntity->ruleFileId->id }}">
								<span class="fname"><span class="mono">{{ $fileEntity->path }}</span>@if ($fileEntity->summary)<small>{{ $fileEntity->summary }}</small>@endif</span>
								<span class="hint">{{ $fileEntity->group->label() }}</span>
								<span class="lines">{{ $fileEntity->lines }}</span>
								<x-icon name="chev" small/>
							</button>
						@endforeach
					</div>
				</section>
			@endif
		@endif

		<section class="section">
			<div class="section-head"><h2>{{ __('Toestemmingen') }}</h2><span class="note">{{ __('project en globaal stapelen') }}</span></div>
			<div class="card" style="padding:16px 18px;display:flex;flex-direction:column;gap:14px">
				<div style="display:flex;flex-direction:column;gap:8px">
					<span class="hint">{{ __('Van dit project') }} · {{ trans_choice(':count regel|:count regels', $projectPermissions->count(), ['count' => $projectPermissions->count()]) }}</span>
					<span class="chips">@forelse ($projectPermissions->take(8) as $permission)<span class="path {{ $permission->tone() }}" title="{{ $permission->label() }} · {{ $permission->source }}">{{ $permission->pattern }}</span>@empty<span class="hint">{{ __('geen') }}</span>@endforelse @if ($projectPermissions->count() > 8)<span class="hint">{{ __('nog :count', ['count' => $projectPermissions->count() - 8]) }}</span>@endif</span>
				</div>
				<div style="display:flex;flex-direction:column;gap:8px">
					<span class="hint">{{ __('Globaal, geldt hier ook') }} · {{ trans_choice(':count regel|:count regels', $globalPermissions->count(), ['count' => $globalPermissions->count()]) }}</span>
					<span class="chips"><button class="btn text xs" type="button" style="padding:0" wire:click="showGlobal">{{ __('Bekijken onder Globaal') }}</button></span>
				</div>
				<span class="hint" style="padding-top:12px;border-top:1px solid var(--line)">{{ __('Grijs mag, rood nooit, oranje vraagt eerst. Een weigering wint altijd, uit welk bestand hij ook komt.') }}</span>
			</div>
		</section>
	</div>
</div>
