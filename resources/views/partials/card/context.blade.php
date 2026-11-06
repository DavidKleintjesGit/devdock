<div class="dbody workspace card-context">
	<x-panel :title="trans_choice(':count bron|:count bronnen', $contextCount, ['count' => $contextCount])" :meta="$contextExplanation" :padded="false">
		<x-slot:actions>
			<label class="context-choice">
				<span class="sr">{{ __('Context van') }}</span>
				<select class="input" wire:model.live="contextChoice">
					@foreach ($contextChoices as $group => $options)
						<optgroup label="{{ $group }}">
							@foreach ($options as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
						</optgroup>
					@endforeach
				</select>
			</label>
		</x-slot:actions>

		<div class="context-columns">
			<nav class="context-tree" aria-label="{{ __('Bronnen') }}">
				@forelse ($contextGroups as $group => $files)
					@if ($group !== '')<span class="context-folder"><x-icon name="folder" small/>{{ $group }}</span>@endif
					@foreach ($files as $file)
						<button type="button" @class(['context-file', 'on' => $contextOpen?->key === $file->key])
							@if ($file->tab) wire:click="setDrawerTab('{{ $file->tab }}')" @else wire:click="openContextFile('{{ $file->key }}')" @endif
							@if ($contextOpen?->key === $file->key) aria-current="true" @endif>
							<x-icon name="file" small/>
							<span class="grow">
								<span class="name">{{ $file->name }}</span>
								@if ($file->path)<span class="path mono">{{ $file->path }}</span>@elseif ($file->description)<span class="hint">{{ $file->description }}</span>@endif
							</span>
						</button>
					@endforeach
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
							<span>{{ $contextOpen->group ?? $contextOpen->description }}</span>
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
