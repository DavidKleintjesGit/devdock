@props(['property', 'tone' => 'done', 'text' => null])

{{-- One notice in the strip under the tabs or the page head. A done notice clears itself after ten seconds, unless a newer one took its place. --}}
<div {{ $attributes->class(['notice', $tone]) }} role="{{ $tone === 'done' ? 'status' : 'alert' }}" wire:key="notice-{{ $property }}-{{ md5((string) $slot) }}"
	@if ($tone === 'done') x-data x-init="const shown = @js((string) $slot); setTimeout(() => { if ($wire.get('{{ $property }}') === shown) $wire.set('{{ $property }}', null) }, 10000)" @endif>
	<span class="notice-icon"><x-icon :name="$tone === 'done' ? 'check' : 'alert'" small/></span>
	<span class="notice-text"><b>{{ $slot }}</b>@if ($text)<span>{{ $text }}</span>@endif</span>
	{{ $actions ?? '' }}
	<button class="icon-btn" type="button" wire:click="$set('{{ $property }}', null)" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button>
</div>
