@props([
	'action',
	'busy' => __('Bezig...'),
	'icon' => null,
])

{{-- Een knop die tijdens zijn eigen actie op slot gaat en zegt dat hij bezig is. --}}
<button
	{{ $attributes->merge(['class' => 'btn']) }}
	type="button"
	wire:click="{{ $action }}"
	wire:target="{{ $action }}"
	wire:loading.attr="disabled"
>
	<span wire:loading.remove wire:target="{{ $action }}" class="lbl">
		@if ($icon)<x-icon :name="$icon" small/>@endif{{ $slot }}
	</span>
	<span wire:loading wire:target="{{ $action }}" class="lbl">
		<span class="dot amber pulse"></span>{{ $busy }}
	</span>
</button>
