<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('skills') }}"><x-icon name="back" small/>{{ __('Skills') }}</a>
			<span class="eyebrow">{{ __('Skills') }}</span>
			<h1>{{ $kind === 'global' ? __('Globaal') : __('Plugins') }}</h1>
			<span class="lede">{{ $kind === 'global' ? __('Alleen voor jou, in elk project. Staat in :folder, niet in een repo.', ['folder' => '~/.claude/skills']) : __('Komen met een geïnstalleerde plugin mee. Je leest ze hier; aanpassen doe je in de plugin.') }}</span>
		</div>
		@if ($kind === 'global')
			<button class="btn primary" type="button" wire:click="startCreating"><x-icon name="plus" small/>{{ __('Skill maken') }}</button>
		@endif
	</div>
</header>

<div class="pbody">
	<div class="stack">
		<section class="section">
			<div class="section-head"><h2>{{ $kind === 'global' ? __('Globale skills') : __('Skills uit plugins') }}</h2><span class="note">{{ trans_choice(':count skill|:count skills', $skills->count(), ['count' => $skills->count()]) }}</span></div>
			@if ($skills->isEmpty())
				<div class="empty"><b>{{ $kind === 'global' ? __('Nog geen globale skills') : __('Geen plugin met skills') }}</b><span>{{ $kind === 'global' ? __('Een globale skill kan een agent in elk project pakken.') : __('Er is geen plugin met skills geïnstalleerd.') }}</span>@if ($kind === 'global')<button class="btn sm" type="button" wire:click="startCreating">{{ __('Skill maken') }}</button>@endif</div>
			@else
				<div class="list">
					@foreach ($skills as $skillEntity)
						<button class="row tall" type="button" wire:click="open('{{ $skillEntity->project }}|{{ $skillEntity->name }}')" wire:key="ls-{{ $skillEntity->skillId->id }}">
							<span class="title" style="width:250px"><b class="mono" style="font-weight:500;font-size:13px">{{ $skillEntity->name }}</b><small>{{ $skillEntity->plugin ?? 'SKILL.md' }}</small></span>
							<span class="grow ellipsis" style="font-size:13px;color:var(--fg-2)">{{ $skillEntity->description }}</span>
							<x-icon name="chev" small/>
						</button>
					@endforeach
				</div>
			@endif
		</section>

		@if ($kind === 'global')
			<p class="notebox">{{ __('Moet een collega een skill ook hebben? Zet hem dan in een bibliotheek of in het project zelf. Wat hier staat, ziet alleen jij.') }}</p>
		@endif
	</div>
</div>
