<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-wb-theme="{{ config('devdock.theme') }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>{{ $title }} · DevDock</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
	@vite(['resources/css/app.css'])
</head>
<body>
@include('partials.icons')

<main class="auth">
	<span class="auth-brand">
		<x-mark :size="40"/>
		<span class="wordmark">Dev<span>Dock</span></span>
	</span>

	{{ $slot }}
</main>
</body>
</html>
