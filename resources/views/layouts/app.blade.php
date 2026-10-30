<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-wb-theme="{{ config('devdock.theme') }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>{{ $title ?? 'DevDock' }}</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap">
	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="working" aria-hidden="true"><span></span></div>
@include('partials.icons')

<div class="shell">
	<aside class="side" aria-label="DevDock">
		<div class="brand">
			<div class="logo" aria-hidden="true">D</div>
			<div class="label"><b>DevDock</b><span class="mono muted">devdock.test</span></div>
		</div>
		<nav class="nav" aria-label="{{ __('Hoofdmenu') }}">
			@foreach ($navigation as $route => $item)
				<a href="{{ route($route) }}"@if (request()->routeIs($route)) aria-current="page"@endif><x-icon :name="$item['icon']"/><span class="label">{{ __($item['label']) }}</span></a>
			@endforeach
		</nav>
		<div class="side-foot">
			<livewire:fetch-status/>
			<div class="version mono">
				<span>devdock v{{ config('devdock.version') }}</span>
				<a href="{{ route('locale', $otherLocale) }}">{{ strtoupper(app()->getLocale()) }}</a>
			</div>
		</div>
	</aside>

	{{ $slot }}

	<nav class="tabbar" aria-label="{{ __('Hoofdmenu') }}">
		@foreach ($navigation as $route => $item)
			<a href="{{ route($route) }}"@if (request()->routeIs($route)) aria-current="page"@endif><span class="pillbg"><x-icon :name="$item['icon']"/></span>{{ __($item['label']) }}</a>
		@endforeach
	</nav>
</div>

<livewire:settings/>

</body>
</html>
