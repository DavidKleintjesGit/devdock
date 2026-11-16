<div class="dbody workspace card-context">
	<x-panel :title="trans_choice(':count bron|:count bronnen', $contextCount, ['count' => $contextCount])" :meta="__('ca. :tokens tokens', ['tokens' => $contextTokens])" :padded="false">
		<x-slot:actions>
			<label class="context-choice">
				<span class="hint">{{ __('Voor') }}</span>
				<select class="input" wire:model.live="contextChoice">
					@foreach ($contextSubjects as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
				</select>
			</label>
			<div class="segmented context-scopes" role="group" aria-label="{{ __('Welke bronnen') }}">
				@foreach ($contextScopes as $contextScopeOption)
					<button type="button" wire:click="setContextScope('{{ $contextScopeOption['case']->value }}')" aria-pressed="{{ $contextScopeOption['case'] === $contextScope ? 'true' : 'false' }}">
						{{ $contextScopeOption['case']->label() }}<span class="note">{{ $contextScopeOption['count'] }}</span>
					</button>
				@endforeach
			</div>
		</x-slot:actions>

		<div class="context-columns">
			<nav class="context-tree" aria-label="{{ __('Bronnen') }}">
				@forelse ($contextOrigins as $contextOrigin)
					<details class="context-root" wire:key="origin-{{ $contextOrigin['origin']->value }}" wire:ignore.self @if ($contextOrigin['isOpen']) open @endif>
						<summary>
							<x-icon name="chev" small/>
							<span class="grow"><b>{{ $contextOrigin['origin']->label() }}</b><span class="origin-hint">{{ $contextOrigin['origin']->hint() }}</span></span>
							<span class="count">{{ $contextOrigin['count'] }}</span>
							<span class="tokens">{{ $contextOrigin['tokensLabel'] }}</span>
						</summary>
						<div class="context-node-children">
							@foreach ($contextOrigin['files'] as $source)
								<button type="button" @class(['context-file', 'on' => $contextOpen?->key === $source['file']->key])
									@if ($source['file']->tab) wire:click="setDrawerTab('{{ $source['file']->tab }}')" @else wire:click="openContextFile('{{ $source['file']->key }}')" @endif
									@if ($contextOpen?->key === $source['file']->key) aria-current="true" @endif>
									<x-icon name="file" small/>
									<span class="grow"><span class="name" title="{{ $source['name'] }}">{{ $source['name'] }}</span></span>
									@if ($source['audience']->value === 'both')
										<span class="audience">{{ __('gedeeld') }}</span>
									@elseif ($source['own'])
										<span class="audience own">{{ __('alleen hij') }}</span>
									@else
										<span @class(['audience', $source['audience']->value])>{{ $source['audience']->shortLabel() }}</span>
									@endif
									<span class="tokens">{{ $source['tokensLabel'] }}</span>
								</button>
							@endforeach
						</div>
					</details>
				@empty
					<p class="hint" style="padding:8px 10px">{{ __('Hier is nog niets meegegeven.') }}</p>
				@endforelse
			</nav>

			@if ($contextOpen)
				<article class="context-reader" aria-label="{{ $contextOpen->name }}">
					<div class="context-reader-head">
						<div class="context-reader-title">
							<button class="btn xs text" type="button" wire:click="closeContextFile"><x-icon name="back" small/>{{ __('Overzicht') }}</button>
							<b>{{ $contextOpen->name }}</b>
							@if ($contextOpen->path)<button class="btn xs" type="button" x-data @click="navigator.clipboard.writeText(@js($contextOpen->path))">{{ __('Kopieer pad') }}</button>@endif
							<button class="btn xs" type="button" x-data @click="navigator.clipboard.writeText(@js($contextOpen->contents))">{{ __('Kopiëren') }}</button>
						</div>
						@if ($contextOpen->path)<span class="mono path">{{ $contextOpen->path }}</span>@endif
						<p class="context-origin">{{ $contextOpen->origin() }}</p>
						<div class="context-reader-meta">
							@if ($contextOpenAudience)<span>{{ __('Krijgen') }}: <b>{{ $contextOpenAudience->label() }}</b></span>@endif
							@if ($contextOpenTokens)<span>· {{ __('ca. :tokens tokens', ['tokens' => $contextOpenTokens]) }}</span>@endif
							<span class="grow"></span>
							@if ($contextOpen->settingsRoute())
								<a class="btn xs" href="{{ route($contextOpen->settingsRoute()) }}">{{ $contextOpen->settingsLabel() }}<x-icon name="arrow" small/></a>
							@endif
						</div>
					</div>
					<div class="md">{!! $contextHtml !!}</div>
				</article>
			@else
				<section class="context-overview" aria-label="{{ __('Overzicht') }}">
					<h4>{{ __('Wat :subject krijgt', ['subject' => $contextSubjectLabel]) }}</h4>
					<div class="context-bar">
						@foreach ($contextShare as $share)
							<span class="{{ $share['origin']->tone() }}" style="width:{{ $share['percent'] }}%" title="{{ $share['origin']->label() }} · {{ $share['percent'] }}%"></span>
						@endforeach
					</div>
					<div class="context-legend">
						@foreach ($contextShare as $share)
							<span><i class="{{ $share['origin']->tone() }}"></i>{{ $share['origin']->label() }}<b>{{ $share['percent'] }}%</b></span>
						@endforeach
					</div>
				</section>
			@endif
		</div>
	</x-panel>
</div>
