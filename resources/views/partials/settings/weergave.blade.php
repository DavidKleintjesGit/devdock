<div class="panelhead"><h3>{{ __('Weergave') }}</h3></div>

<div class="set">
	<div class="txt"><span class="lbl">{{ __('Thema') }}</span></div>
	<div class="seg" role="group" aria-label="{{ __('Thema') }}">
		@foreach (['system' => __('Systeem'), 'light' => __('Licht'), 'dark' => __('Donker')] as $value => $label)
			<button type="button" aria-pressed="{{ $theme === $value ? 'true' : 'false' }}" wire:click="$set('theme', '{{ $value }}')">{{ $label }}</button>
		@endforeach
	</div>
</div>

<div class="set">
	<div class="txt"><label class="lbl" for="language">{{ __('Taal') }}</label></div>
	<select id="language" class="select" wire:model="language">
		<option value="en">English</option>
		<option value="nl">Nederlands</option>
	</select>
</div>

<div class="set">
	<div class="txt"><span class="lbl">{{ __('Branchnaam op de kaart') }}</span></div>
	<button class="toggle" type="button" aria-pressed="{{ $showBranch ? 'true' : 'false' }}" wire:click="$toggle('showBranch')" aria-label="{{ __('Branchnaam op de kaart') }}"></button>
</div>
