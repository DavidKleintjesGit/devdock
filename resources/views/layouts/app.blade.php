<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-wb-theme="{{ config('devdock.theme') }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>{{ $title ? $title . ' · DevDock' : 'DevDock' }}</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="working" aria-hidden="true"><span></span></div>
@include('partials.icons')

<div class="shell">
	<aside class="side" aria-label="DevDock">
		<a class="brand" href="{{ route('board') }}">
			<x-mark/>
			<span class="wordmark">Dev<span>Dock</span></span>
		</a>

		<nav class="nav" aria-label="{{ __('Hoofdmenu') }}">
			@foreach ($navigation as $route => $item)
				<a href="{{ route($route) }}"@if (request()->routeIs($route)) aria-current="page"@endif>
					<x-icon :name="$item['icon']"/>
					<span class="nav-label">{{ __($item['label']) }}</span>
					@if ($item['pill'])<span class="pill" title="{{ trans_choice(':count taak is jouw beurt|:count taken zijn jouw beurt', $item['pill'], ['count' => $item['pill']]) }}">{{ $item['pill'] }}</span>@endif
					@if ($item['count'])<span class="nav-count" title="{{ trans_choice(':count sessie kan weg|:count sessies kunnen weg', $item['count'], ['count' => $item['count']]) }}">{{ $item['count'] }}</span>@endif
					@if ($item['flag'])<span class="flag" title="{{ $item['flag'] }}"></span>@endif
				</a>
			@endforeach
		</nav>

		<div class="side-foot">
			<livewire:fetch-status/>
			<div class="nav">
				<button type="button" onclick="Livewire.dispatch('open-settings')">
					<x-icon name="instellingen"/>
					<span class="nav-label">{{ __('Instellingen') }}</span>
				</button>
				<details class="menu side-menu">
					<summary>
						<x-icon name="uitloggen"/>
						<span class="nav-label">{{ __('Uitloggen') }}</span>
					</summary>
					<div class="menu-pop up" role="menu">
						<form method="post" action="{{ route('logout') }}">
							@csrf
							@method('DELETE')
							<button type="submit" role="menuitem">
								<x-icon name="uitloggen"/>
								<span><b>{{ __('Uitloggen') }}</b><small>{{ __('Alleen op dit apparaat.') }}</small></span>
							</button>
						</form>
						<form method="post" action="{{ route('devices.destroy') }}">
							@csrf
							@method('DELETE')
							<button type="submit" role="menuitem">
								<x-icon name="apparaten"/>
								<span><b>{{ __('Overal uitloggen') }}</b><small>{{ __('Ook je telefoon en elk ander onthouden apparaat. Voor als je telefoon kwijt is.') }}</small></span>
							</button>
						</form>
					</div>
				</details>
			</div>
		</div>
	</aside>

	{{ $slot }}

	<nav class="tabbar" aria-label="{{ __('Hoofdmenu') }}">
		@foreach ($navigation as $route => $item)
			<a href="{{ route($route) }}"@if (request()->routeIs($route)) aria-current="page"@endif>
				<x-icon :name="$item['icon']"/>{{ __($item['label']) }}
				@if ($item['pill'])<span class="pill">{{ $item['pill'] }}</span>@endif
			</a>
		@endforeach
	</nav>
</div>

<livewire:settings/>

</body>
</html>
