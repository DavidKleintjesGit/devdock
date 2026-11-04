<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('rules') }}"><x-icon name="back" small/>{{ __('Regels') }}</a>
			<span class="eyebrow">{{ __('Bibliotheek') }}</span>
			<h1>{{ $library->name }}</h1>
			<span class="lede">{{ __('Leidend: elk project dat hem volgt, wordt met deze versies vergeleken. Een agent leest hem nooit zelf, alleen wat in het project staat.') }}</span>
		</div>
		<button class="btn primary" type="button" wire:click="startRule"><x-icon name="plus" small/>{{ __('Regel toevoegen') }}</button>
	</div>
</header>

<div class="pbody">
	<div class="stack">
		<div class="list">
			<div class="row" style="min-height:60px;padding:10px 18px">
				<span class="grow" style="display:flex;flex-direction:column">
					<span class="hint">{{ __('Gekoppelde map') }}</span>
					@if ($library->folder)
						<span class="mono" style="font-size:13px">{{ $library->folder }}</span>
						<span class="hint">{{ __('wijzigingen komen binnen als nieuwe versie') }}</span>
					@else
						<span>{{ __('geen; DevDock is de bron') }}</span>
					@endif
				</span>
				@if ($library->folder)
					<x-busy-button action="syncLibrary({{ $library->id }})" class="btn sm" :busy="__('Binnenhalen...')">{{ __('Binnenhalen') }}</x-busy-button>
					<x-busy-button action="writeToFolder({{ $library->id }})" class="btn sm" :busy="__('Schrijven...')">{{ __('Naar de map schrijven') }}</x-busy-button>
				@endif
			</div>
		</div>

		<section class="section">
			<div class="section-head"><h2>{{ trans_choice(':count regel|:count regels', $rows->count(), ['count' => $rows->count()]) }}</h2><span class="note">{{ trans_choice(':count project volgt hem|:count projecten volgen hem', count($library->followers), ['count' => count($library->followers)]) }}</span></div>
			@if ($rows->isEmpty())
				<div class="empty">
					<b>{{ __('Nog geen regels') }}</b>
					<span>{{ __('Maak er een, of zet er een terug vanuit een project met "Terugzetten naar de bibliotheek".') }}</span>
					<button class="btn sm" type="button" wire:click="startRule">{{ __('Regel toevoegen') }}</button>
				</div>
			@else
				<div class="list">
					<table class="table">
						<thead><tr><th>{{ __('Regel') }}</th><th>{{ __('In projecten') }}</th><th>{{ __('Mist bij een volger') }}</th></tr></thead>
						<tbody>
							@foreach ($rows as $row)
								<tr class="click" wire:click="showRule({{ $library->id }}, '{{ $row['item']->name }}')" wire:key="lr-{{ $row['item']->id }}">
									<td><span class="mono" style="font-weight:500">{{ $row['item']->name }}</span><br><span class="hint">{{ $row['latest']?->label() }} · {{ trans_choice(':count regel|:count regels', $row['latest']?->lines() ?? 0, ['count' => $row['latest']?->lines() ?? 0]) }} · {{ $row['latest']?->createdAt?->toDisplayZone()->translatedFormat('j M') }}</span></td>
									<td>
										<span class="hint">{{ trans_choice(':count volger|:count volgers', $row['followers'], ['count' => $row['followers']]) }}</span>
										<span class="chips" style="margin-top:4px">
											@if ($row['equal'])<span class="label green">{{ __(':count gelijk', ['count' => $row['equal']]) }}</span>@endif
											@if ($row['behind'])<span class="label amber">{{ __(':count achter', ['count' => $row['behind']]) }}</span>@endif
											@if ($row['modified'])<span class="label blue">{{ __(':count zelf aangepast', ['count' => $row['modified']]) }}</span>@endif
										</span>
									</td>
									<td class="hint">{{ $row['missing'] === [] ? __('bij niemand') : implode(', ', $row['missing']) }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@endif
		</section>

		<section class="section">
			<div class="section-head"><h2>{{ __('Wat de standen betekenen') }}</h2></div>
			<div class="list">
				@foreach ($standingCases as $case)
					<div class="row" style="align-items:flex-start;padding:12px 18px">
						<span style="width:130px;flex:none"><span class="label {{ $case->tone() }}">{{ $case->label() }}</span></span>
						<span class="hint" style="font-size:13px">{{ $case->explanation() }}</span>
					</div>
				@endforeach
			</div>
		</section>

		@if ($withoutLibrary->isNotEmpty())
			<p class="notebox">
				<b>{{ trans_choice(':count project heeft nog geen enkele bibliotheekregel|:count projecten hebben nog geen enkele bibliotheekregel', $withoutLibrary->count(), ['count' => $withoutLibrary->count()]) }}</b><br>
				<span class="hint">{{ $withoutLibrary->take(5)->implode(', ') }}{{ $withoutLibrary->count() > 5 ? ' ' . __('en nog :count', ['count' => $withoutLibrary->count() - 5]) : '' }}.</span>
			</p>
		@endif
	</div>
</div>
