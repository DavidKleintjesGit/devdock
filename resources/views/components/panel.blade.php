@props(['title' => null, 'meta' => null, 'padded' => true])

{{-- Een wit paneel op de grijze werkruimte van een kaart, met een kop en eventueel een voet. --}}
<section {{ $attributes->class(['panel']) }}>
	@if ($title)
		<header class="panel-head">
			<h3>{{ $title }}</h3>
			@if ($meta)<span class="hint">{{ $meta }}</span>@endif
			<span class="grow"></span>
			{{ $actions ?? '' }}
		</header>
	@endif
	<div @class(['panel-body', 'padded' => $padded])>{{ $slot }}</div>
	@isset($footer)
		<footer class="panel-foot">{{ $footer }}</footer>
	@endisset
</section>
