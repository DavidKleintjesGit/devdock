<figure class="mockup" x-data="{ big: false }" :class="{ big }" @keydown.escape.window="big = false" wire:ignore>
	<figcaption class="mockup-bar">
		<x-icon name="layout" small/>
		<span class="grow">{{ $stylesheetNames !== '' ? __('Mockup · met :sheets', ['sheets' => $stylesheetNames]) : __('Mockup') }}</span>
		<button class="btn xs" type="button" @click="big = ! big" x-text="big ? @js(__('Kleiner')) : @js(__('Groot openen'))">{{ __('Groot openen') }}</button>
	</figcaption>
	<iframe class="mockup-frame" sandbox="allow-scripts allow-same-origin" srcdoc="{{ $document }}" title="{{ __('Mockup') }}" loading="lazy"></iframe>
</figure>
