<div class="panelhead"><h3>{{ __('Ophalen') }}</h3></div>

<div class="set">
	<div class="txt"><label class="lbl" for="refresh">{{ __('Automatisch ophalen') }}</label></div>
	<select id="refresh" class="select" wire:model="refreshSeconds">
		<option value="0">{{ __('Uit, alleen handmatig') }}</option>
		<option value="300">{{ __('Elke 5 minuten') }}</option>
		<option value="900">{{ __('Elke 15 minuten') }}</option>
		<option value="3600">{{ __('Elk uur') }}</option>
	</select>
</div>

<div class="set" @if ($run?->isRunning()) wire:poll.2s @endif>
	<div class="txt">
		<span class="lbl">{{ __('Nu ophalen') }}</span>
		@if ($run?->isRunning())
			<span class="hint"><span class="dot amber pulse"></span> {{ $run->source ?? '…' }} · {{ $run->seconds() }}s</span>
		@elseif ($run)
			<span class="hint">{{ $run->resultLine() }}</span>
			@if ($run->message)<span class="hint bad"><x-icon name="alert" small/>{{ $run->message }}</span>@endif
		@endif
	</div>
	<button class="btn" type="button" wire:click="fetchNow" @disabled($run?->isRunning())>
		<x-icon name="refresh" small/>{{ $run?->isRunning() ? __('Bezig...') : __('Nu ophalen') }}
	</button>
</div>
