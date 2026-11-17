@props([
	'action',
	'busy' => __('Bezig...'),
	'icon' => null,
	'pending' => false,
])

{{-- A button that stays busy while its action runs: during its own request, and while the background task it started runs or waits in line. --}}
<button
	{{ $attributes->except('disabled')->merge(['class' => 'btn' . ($pending ? ' is-busy' : '')]) }}
	type="button"
	wire:click="{{ $action }}"
	wire:target="{{ $action }}"
	wire:loading.attr="disabled"
	wire:loading.class="is-busy"
	@disabled($pending || $attributes->get('disabled'))
	@if ($pending) aria-busy="true" @endif
>
	@if ($pending)
		<span class="lbl"><span class="spin" aria-hidden="true"></span>{{ $busy }}</span>
	@else
		<span wire:loading.remove wire:target="{{ $action }}" class="lbl">
			@if ($icon)<x-icon :name="$icon" small/>@endif{{ $slot }}
		</span>
		<span wire:loading wire:target="{{ $action }}" class="lbl">
			<span class="spin" aria-hidden="true"></span>{{ $busy }}
		</span>
	@endif
</button>
