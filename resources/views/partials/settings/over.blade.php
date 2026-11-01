<header style="flex-direction:row;align-items:center;gap:14px">
	<x-mark :size="44"/>
	<span style="display:flex;flex-direction:column;gap:4px">
		<span class="wordmark" style="font-size:22px">Dev<span>Dock</span></span>
		<p>{{ __('Een werkbord dat vier bronnen op één plek legt. Alles draait en blijft op deze machine.') }}</p>
	</span>
</header>

<section>
	<div class="setlist">
		<div class="set"><span class="txt"><b>{{ __('Versie') }}</b></span><span class="mono">devdock v{{ config('devdock.version') }}</span></div>
		<div class="set"><span class="txt"><b>{{ __('Database') }}</b></span><span class="mono">{{ $database }}</span></div>
		<div class="set"><span class="txt"><b>{{ __('Instellingen') }}</b></span><span class="mono">.env</span></div>
		<div class="set"><span class="txt"><b>{{ __('Regelbestanden') }}</b></span><span>{{ $counts['rules'] }}</span></div>
		<div class="set"><span class="txt"><b>{{ __('Skills') }}</b></span><span>{{ $counts['skills'] }}</span></div>
		<div class="set"><span class="txt"><b>{{ __('Sessies') }}</b></span><span>{{ $counts['sessions'] }}</span></div>
	</div>
</section>
