<x-app-layout :title="$page . ' · DevDock'">
<div class="page">
	<header class="pagebar">
		<div class="logo m-logo" aria-hidden="true">D</div>
		<h1>{{ __($page) }}</h1>
		<div class="actions"></div>
	</header>
	<div class="content">
		<div class="box">
			<div class="empty">
				<span class="bubble"><x-icon :name="$icon"/></span>
				<b>{{ __(':page komt nog', ['page' => __($page)]) }}</b>
				<span>{{ __('Deze pagina staat in het ontwerp maar is nog niet gebouwd.') }}</span>
				<a class="btn" href="{{ route('board') }}">{{ __('Terug naar het bord') }}</a>
			</div>
		</div>
	</div>
</div>
</x-app-layout>
