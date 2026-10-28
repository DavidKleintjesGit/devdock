<div class="panelhead"><h3>{{ __('Mappen') }}</h3></div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="sites-path">{{ __('Projecten') }}</label>
		@if ($folders['sites'])
			<span class="hint ok"><x-icon name="check" small/>{{ __(':count projecten', ['count' => $counts['projects']]) }}</span>
		@else
			<span class="hint bad"><x-icon name="alert" small/>{{ __('Bestaat niet') }}</span>
		@endif
	</div>
	<input id="sites-path" class="input mono" type="text" wire:model.live.debounce.500ms="sitesPath" autocomplete="off">
</div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="sessions-path">{{ __('Sessies') }}</label>
		@if ($folders['sessions'])
			<span class="hint ok"><x-icon name="check" small/>{{ __(':count sessies', ['count' => $counts['sessions']]) }}</span>
		@else
			<span class="hint bad"><x-icon name="alert" small/>{{ __('Bestaat niet') }}</span>
		@endif
	</div>
	<input id="sessions-path" class="input mono" type="text" wire:model.live.debounce.500ms="sessionsPath" autocomplete="off">
</div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="core-path">{{ __('mtvtd-core') }}</label>
		@if ($folders['core'])
			<span class="hint ok"><x-icon name="check" small/>{{ __(':count regelbestanden', ['count' => $counts['rules']]) }}</span>
		@else
			<span class="hint bad"><x-icon name="alert" small/>{{ __('Bestaat niet') }}</span>
		@endif
	</div>
	<input id="core-path" class="input mono" type="text" wire:model.live.debounce.500ms="corePath" autocomplete="off">
</div>
