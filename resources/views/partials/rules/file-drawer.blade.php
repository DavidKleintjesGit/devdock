<div class="scrim" wire:click="closeFile"></div>
<aside class="drawer wide" role="dialog" aria-labelledby="file-title">
	<div class="dbar">
		<span class="kind">{{ __('Regelbestand') }} · {{ $project }}</span>
		<details class="menu" x-data="{ open: false }" :open="open" @click.outside="open = false">
			<summary class="icon-btn filled" aria-label="{{ __('Acties') }}" @click.prevent="open = ! open"><x-icon name="meer"/></summary>
			<div class="menu-pop" role="menu">
				<button type="button" role="menuitem" wire:click="toggleFavorite('{{ $project }}', '{{ $path }}')"><x-icon name="star" small/>{{ $isFavorite ? __('Geen favoriet meer') : __('Favoriet') }}</button>
				@if ($standing)
					<button type="button" role="menuitem" wire:click="showRule({{ $standing->item->libraryId }}, '{{ $standing->item->name }}')">{{ __('Naar de bibliotheekregel') }}</button>
				@endif
			</div>
		</details>
		<button class="icon-btn" type="button" wire:click="closeFile" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<span class="meta">
			@if ($standing)<span class="label {{ $standing->standing->tone() }}">{{ $standing->standing->label() }}</span><span class="hint">{{ __('uit :library', ['library' => $standing->item->library]) }}</span>@endif
		</span>
		<h2 id="file-title" class="mono" style="font-size:18px">{{ $path }}</h2>
		<span class="sub">{{ $file ? trans_choice(':count regel|:count regels', $file->lines, ['count' => $file->lines]) : '' }}</span>
	</div>

	<nav class="tabs" role="tablist" style="margin-top:10px">
		@foreach (['Inhoud', 'Vergelijken', 'In andere projecten'] as $name)
			<button type="button" role="tab" aria-selected="{{ $fileTab === $name ? 'true' : 'false' }}" wire:click="$set('fileTab', '{{ $name }}')">{{ __($name) }}@if ($name === 'In andere projecten')<span class="n">{{ $others->count() }}</span>@endif</button>
		@endforeach
	</nav>

	<div class="dbody">
		@if ($fileTab === 'Vergelijken')
			<section class="dsec">
				@if ($choices === [])
					<p class="muted">{{ __('Er is niets om mee te vergelijken: geen bibliotheekversie en geen ander project met dit bestand.') }}</p>
				@else
					<div class="field">
						<label for="compare-with">{{ __('Vergelijk met') }}</label>
						<select id="compare-with" class="input" wire:model.live="compareWith">
							@foreach ($choices as $key => $label)<option value="{{ $key }}" @selected($key === $target)>{{ $label }}</option>@endforeach
						</select>
					</div>
					@if ($standing && $standing->onVersion && str_starts_with($target, 'v:'))
						<div class="alert teal"><x-icon name="info" small/><span>{{ $standing->isCurrent() ? __('Dit is precies de nieuwste versie uit :library.', ['library' => $standing->item->library]) : __('Dit is precies :version uit :library. Wat hieronder bijkwam staat in nieuwere versies; hier is niets zelf veranderd, dus bijwerken verliest niets.', ['version' => $standing->onVersion->label(), 'library' => $standing->item->library]) }}</span></div>
					@endif
					@if ($diff)
						<x-diff :hunks="$diff" :empty="__('Geen verschil.')"/>
					@endif
				@endif
			</section>
		@elseif ($fileTab === 'In andere projecten')
			<section class="dsec">
				@if ($others->isEmpty())
					<p class="muted">{{ __('Geen ander project heeft een bestand met deze naam.') }}</p>
				@else
					<div class="list">
						@foreach ($others as $other)
							<div class="row" wire:key="o-{{ $other->ruleFileId->id }}">
								<span class="title grow"><b>{{ $other->project }}</b><small class="mono">{{ $other->path }} · {{ trans_choice(':count regel|:count regels', $other->lines, ['count' => $other->lines]) }}</small></span>
								<button class="btn xs" type="button" wire:click="compareWithProject('{{ $other->project }}')">{{ __('Vergelijken') }}</button>
							</div>
						@endforeach
					</div>
				@endif
			</section>
		@else
			<section class="dsec">
				@if ($contents === null)
					<p class="muted">{{ __('Het bestand staat niet op schijf.') }}</p>
				@else
					<pre class="code-block" style="white-space:pre-wrap;max-height:none">{{ $contents }}</pre>
				@endif
			</section>
		@endif
	</div>

	<div class="dfoot">
		@if ($standing)
			@if ($standing->isModified())
				<button class="btn" type="button" wire:click="pushBackFile">{{ __('Terugzetten naar de bibliotheek') }}</button>
			@endif
			<span class="hint">{{ $standing->detail() }}</span>
			<button class="btn primary" type="button" wire:click="updateFile" @disabled($standing->isCurrent())>{{ __('Bijwerken vanuit de bibliotheek') }}</button>
		@else
			<span class="hint">{{ __('Dit bestand hoort bij geen bibliotheek die het project volgt.') }}</span>
		@endif
	</div>
</aside>
