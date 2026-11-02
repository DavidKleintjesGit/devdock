<div class="dbody context">
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
		</label>
		<p class="hint">{{ $contextExplanation }}</p>
	</div>

	<div class="context-files" role="list">
		@foreach ($contextGroups as $group => $files)
			@if ($group !== '')<span class="context-group">{{ $group }}</span>@endif
			@foreach ($files as $file)
				<button type="button" role="listitem" @class(['context-file', 'on' => $contextOpen?->key === $file->key])
					@if ($file->tab) wire:click="setDrawerTab('{{ $file->tab }}')" @else wire:click="openContextFile('{{ $file->key }}')" @endif
					@if ($contextOpen?->key === $file->key) aria-current="true" @endif>
					<span class="name">{{ $file->name }}</span>
					@if ($file->description)<span class="hint">{{ $file->description }}</span>@endif
				</button>
			@endforeach
		@endforeach
	</div>

	@if ($contextOpen)
		<article class="context-reader" aria-label="{{ $contextOpen->name }}">
			<div class="context-reader-head">
				<span class="grow"><b>{{ $contextOpen->name }}</b>@if ($contextOpen->path)<span class="mono hint">{{ $contextOpen->path }}</span>@endif</span>
				<button class="btn xs" type="button" x-data @click="navigator.clipboard.writeText(@js($contextOpen->contents))">{{ __('Kopiëren') }}</button>
			</div>
			<div class="md">{!! $contextHtml !!}</div>
		</article>
	@else
		<p class="hint" style="padding:0 24px 24px">{{ __('Hier is nog niets meegegeven.') }}</p>
	@endif
</div>
