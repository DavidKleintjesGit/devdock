<div class="panelhead"><h3>{{ __('Drempels') }}</h3></div>

<div class="set">
	<div class="txt"><label class="lbl" for="abandoned">{{ __('Afgebroken tot') }}</label></div>
	<select id="abandoned" class="select" wire:model="abandonedUpTo">
		@foreach ([0, 1, 2, 3, 5] as $value)
			<option value="{{ $value }}">{{ __(':count prompts', ['count' => $value]) }}</option>
		@endforeach
	</select>
</div>

<div class="set">
	<div class="txt"><label class="lbl" for="large">{{ __('Groot vanaf') }}</label></div>
	<select id="large" class="select" wire:model="largeFromMegabytes">
		@foreach ([5, 10, 15, 25, 50] as $value)
			<option value="{{ $value }}">{{ $value }} MB</option>
		@endforeach
	</select>
</div>

<div class="set">
	<div class="txt"><label class="lbl" for="old">{{ __('Oud na') }}</label></div>
	<select id="old" class="select" wire:model="oldAfterDays">
		@foreach ([7, 14, 30] as $value)
			<option value="{{ $value }}">{{ __(':count dagen', ['count' => $value]) }}</option>
		@endforeach
	</select>
</div>
