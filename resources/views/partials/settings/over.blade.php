<div class="panelhead">
	<h3>{{ __('Over DevDock') }}</h3>
	<p>{{ __('Een werkbord dat vier bronnen op één plek legt. Alles draait en blijft op deze machine.') }}</p>
</div>

<dl class="facts">
	<div><dt>{{ __('Versie') }}</dt><dd>devdock v{{ config('devdock.version') }}</dd></div>
	<div><dt>{{ __('Database') }}</dt><dd class="mono">database/database.sqlite</dd></div>
	<div><dt>{{ __('Instellingen') }}</dt><dd class="mono">.env</dd></div>
	<div><dt>{{ __('Regelbestanden') }}</dt><dd>{{ $counts['rules'] }}</dd></div>
	<div><dt>{{ __('Skills') }}</dt><dd>{{ $counts['skills'] }}</dd></div>
	<div><dt>{{ __('Sessies') }}</dt><dd>{{ $counts['sessions'] }}</dd></div>
</dl>
