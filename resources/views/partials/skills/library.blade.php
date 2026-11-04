<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('skills') }}"><x-icon name="back" small/>{{ __('Skills') }}</a>
			<span class="eyebrow">{{ __('Bibliotheek') }}</span>
			<h1>{{ $library->name }}</h1>
			<span class="lede">{{ __('Een versie van een skill is de hele map. Projecten die de bibliotheek volgen, worden ermee vergeleken.') }}</span>
		</div>
		<button class="btn primary" type="button" wire:click="startCreating"><x-icon name="plus" small/>{{ __('Skill maken') }}</button>
	</div>
</header>

<div class="pbody">
	<div class="stack">
		<section class="section">
			<div class="section-head"><h2>{{ trans_choice(':count skill|:count skills', count($library->skills()), ['count' => count($library->skills())]) }}</h2><span class="note">{{ trans_choice(':count project volgt hem|:count projecten volgen hem', count($library->followers), ['count' => count($library->followers)]) }}</span></div>
			@if ($library->skills() === [])
				<div class="empty">
					<b>{{ __('Nog geen skills') }}</b>
					<span>{{ __('Open een skill in een project en kies "In de bibliotheek zetten", of vergelijk eerst de versies die in je projecten staan.') }}</span>
				</div>
			@else
				<div class="list">
					@foreach ($library->skills() as $itemEntity)
						<button class="frow" type="button" wire:click="openLibraryItem({{ $library->id }}, '{{ $itemEntity->name }}')" wire:key="lsk-{{ $itemEntity->id }}">
							<span class="fname"><span class="mono">{{ $itemEntity->name }}</span><small>{{ $itemEntity->latest()?->label() }} · {{ $itemEntity->latest()?->createdAt?->toDisplayZone()->translatedFormat('j M') }}</small></span>
							<span></span>
							<span class="lines">{{ count($itemEntity->versions) }}</span>
							<x-icon name="chev" small/>
						</button>
					@endforeach
				</div>
			@endif
		</section>
	</div>
</div>
