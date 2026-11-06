<div class="dbody workspace card-context">
	<x-panel :title="trans_choice(':count bron|:count bronnen', $contextCount, ['count' => $contextCount])" :meta="$contextExplanation" :padded="false">
		@if ($contextLenses !== [])
			<x-slot:actions>
				<label class="context-choice">
					<span class="sr">{{ __('Lens') }}</span>
					<select class="input" wire:model.live="contextChoice">
						@foreach ($contextLenses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
					</select>
				</label>
			</x-slot:actions>
		@endif

		<div class="context-columns">
			<nav class="context-tree" aria-label="{{ __('Bronnen') }}">
				@forelse ($contextTree as $contextRoot)
					<details class="context-root" open>
						<summary>
							<x-icon name="chev" small/>
							<span class="grow"><b>{{ $contextRoot['label'] }}</b>@if ($contextRoot['path'])<span class="path mono">{{ $contextRoot['path'] }}</span>@endif</span>
							<span class="count">{{ $contextRoot['count'] }}</span>
						</summary>
						<div class="context-node-children">
							@include('partials.card.context-nodes', ['nodes' => $contextRoot['nodes']])
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
							<b>{{ $contextOpen->name }}</b>
							@if ($contextOpen->path)<button class="btn xs" type="button" x-data @click="navigator.clipboard.writeText(@js($contextOpen->path))">{{ __('Kopieer pad') }}</button>@endif
							<button class="btn xs" type="button" x-data @click="navigator.clipboard.writeText(@js($contextOpen->contents))">{{ __('Kopiëren') }}</button>
						</div>
						@if ($contextOpen->path)<span class="mono path">{{ $contextOpen->path }}</span>@endif
						<div class="context-reader-meta">
							@if ($contextOpenAudience)<span>{{ __('Krijgen') }}: <b>{{ $contextOpenAudience->label() }}</b></span>@endif
							@if ($contextOpen->group)<span>· {{ $contextOpen->group }}</span>@endif
							<span class="grow"></span>
							@if ($contextOpen->settingsRoute())
								<a class="btn xs" href="{{ route($contextOpen->settingsRoute()) }}">{{ $contextOpen->settingsLabel() }}<x-icon name="arrow" small/></a>
							@endif
						</div>
					</div>
					<div class="md">{!! $contextHtml !!}</div>
				</article>
			@endif
		</div>
	</x-panel>
</div>
