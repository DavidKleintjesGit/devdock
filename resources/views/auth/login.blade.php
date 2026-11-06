<x-layouts.guest :title="__('Inloggen')">
	@if (! $loginStateObject->hasAccount)
		<section class="auth-card">
			<header>
				<h1>{{ __('Nog geen gebruiker') }}</h1>
				<p>{{ __('DevDock heeft nog geen wachtwoord. Maak op de laptop een gebruiker aan; daarna log je hier in.') }}</p>
			</header>

			<div class="field">
				<span class="eyebrow">{{ __('In de map van DevDock') }}</span>
				<code class="auth-command">php artisan devdock:user</code>
				<span class="help">{{ __('Je typt het wachtwoord daar zelf; het komt nergens in beeld.') }}</span>
			</div>

			<a class="btn" href="{{ route('login') }}">{{ __('Opnieuw kijken') }}</a>
		</section>
	@else
		<form class="auth-card" method="post" action="{{ route('login.store') }}">
			@csrf

			<header>
				<h1>{{ __('Inloggen') }}</h1>
				<p>{{ __('Je bord, je taken en je agents, op elk apparaat.') }}</p>
			</header>

			@if ($loginStateObject->isLockedOut())
				<p class="alert" role="status">
					<x-icon name="klok" :small="true"/>
					<span><b>{{ __('Te vaak geprobeerd.') }}</b> {{ __('Over :time kun je het opnieuw proberen.', ['time' => $loginStateObject->lockedFor()]) }}</span>
				</p>
			@elseif ($loginStateObject->resumeLine() !== null)
				<p class="alert teal" role="status">
					<x-icon name="info" :small="true"/>
					<span><b>{{ __('Je was uitgelogd.') }}</b> {{ $loginStateObject->resumeLine() }}</span>
				</p>
			@endif

			<div class="field">
				<label for="password">{{ __('Wachtwoord') }}</label>
				<input id="password" class="input" type="password" name="password" autocomplete="current-password" required
					@if ($loginStateObject->isLockedOut()) disabled @else autofocus @endif
					@error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
				@error('password')
					<span id="password-error" class="err"><x-icon name="alert" :small="true"/>{{ $message }}</span>
				@enderror
			</div>

			<label class="remember">
				<input type="checkbox" name="remember" value="1" checked>
				<span class="switch" aria-hidden="true"></span>
				<span class="remember-text">
					<span>{{ __('Onthoud dit apparaat') }}</span>
					<span class="help">{{ __('Je blijft ingelogd tot je zelf uitlogt.') }}</span>
				</span>
			</label>

			@if ($loginStateObject->isLockedOut())
				<button class="btn" type="submit" disabled data-unlocks-in="{{ $loginStateObject->secondsLocked }}">{{ __('Wacht nog :time', ['time' => $loginStateObject->lockedFor()]) }}</button>
			@else
				<button class="btn primary" type="submit">{{ __('Inloggen') }}</button>
			@endif
		</form>

		<p class="auth-foot">{{ __('Wachtwoord vergeten? Zet op de laptop een nieuw met') }} <code>php artisan devdock:user</code></p>
	@endif

	<script>
		// counts the wait down on the button and opens the page again once it is over
		document.querySelectorAll('[data-unlocks-in]').forEach((button) => {
			const label = button.textContent.replace(/\d+:\d{2}/, '{time}');
			let left = Number(button.dataset.unlocksIn);

			const tick = () => {
				if (left <= 0) {
					window.location.reload();

					return;
				}

				button.textContent = label.replace('{time}', `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`);
				left--;
				setTimeout(tick, 1000);
			};

			tick();
		});
	</script>
</x-layouts.guest>
