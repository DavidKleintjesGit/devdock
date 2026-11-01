<header>
	<h3>{{ __('Weergave') }}</h3>
	<p>{{ __('Hoe DevDock er op dit apparaat uitziet.') }}</p>
</header>

<section>
	<div class="setlist">
		<div class="set">
			<span class="txt"><b>{{ __('Thema') }}</b></span>
			<span class="seg" role="group" aria-label="{{ __('Thema') }}">
				@foreach (['system' => __('Systeem'), 'light' => __('Licht'), 'dark' => __('Donker')] as $value => $label)
					<button type="button" aria-pressed="{{ $theme === $value ? 'true' : 'false' }}" wire:click="$set('theme', '{{ $value }}')">{{ $label }}</button>
				@endforeach
			</span>
		</div>
		<div class="set">
			<span class="txt"><b>{{ __('Taal') }}</b></span>
			<span class="seg" role="group" aria-label="{{ __('Taal') }}">
				@foreach (['en' => 'English', 'nl' => 'Nederlands'] as $value => $label)
					<button type="button" aria-pressed="{{ $language === $value ? 'true' : 'false' }}" wire:click="$set('language', '{{ $value }}')">{{ $label }}</button>
				@endforeach
			</span>
		</div>
	</div>
</section>
