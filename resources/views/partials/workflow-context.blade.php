<div class="stack context-page">
	<div class="context-pick">
		<label class="context-choice">
			<span>{{ __('Context van') }}</span>
			<select class="input" wire:model.live="contextChoice">
				@foreach ($contextChoices as $group => $options)
					<optgroup label="{{ $group }}">
						@foreach ($options as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
					</optgroup>
				@endforeach
			</select>
			<span class="hint">{{ $isStandard ? __('in elk project') : __('in dit project') }}</span>
		</label>
		<p class="hint">{{ $contextExplanation }}</p>
	</div>

	<div class="context-split">
		<div class="context-files" role="list">
			@forelse ($contextGroups as $group => $files)
				@if ($group !== '')<span class="context-group">{{ $group }}</span>@endif
				@foreach ($files as $file)
					<button type="button" role="listitem" @class(['context-file', 'on' => $contextOpen?->key === $file->key]) wire:click="openContextFile('{{ $file->key }}')" @if ($contextOpen?->key === $file->key) aria-current="true" @endif>
						<span class="name">{{ $file->name }}</span>
						@if ($file->description)<span class="hint">{{ $file->description }}</span>@endif
					</button>
				@endforeach
			@empty
				<p class="hint" style="margin:0;padding:8px 10px">{{ __('Hier is nog niets meegegeven.') }}</p>
			@endforelse
		</div>

		@if ($contextOpen)
			<article class="context-reader" aria-label="{{ $contextOpen->name }}">
				<div class="context-reader-head">
					<span class="grow"><b>{{ $contextOpen->name }}</b>@if ($contextOpen->path)<span class="mono hint">{{ $contextOpen->path }}</span>@endif</span>
					<button class="btn xs" type="button" x-data @click="navigator.clipboard.writeText(@js($contextOpen->contents))">{{ __('Kopiëren') }}</button>
				</div>
				<div class="md">{!! $contextHtml !!}</div>
			</article>
		@endif
	</div>
</div>
