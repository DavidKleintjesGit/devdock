<header>
	<h3>{{ __('Mappen') }}</h3>
	<p>{{ __('Waar DevDock je projecten en je sessies vindt. Een pad wordt meteen gecontroleerd.') }}</p>
</header>

<section>
	<div class="setlist">
		<label class="set stack">
			<span class="txt"><b>{{ __('Projecten') }}</b><small style="color:{{ $folders['sites'] ? 'var(--green-dot)' : 'var(--red)' }}">{{ $folders['sites'] ? trans_choice(':count project gevonden|:count projecten gevonden', $counts['projects'], ['count' => $counts['projects']]) : __('Deze map bestaat niet.') }}</small></span>
			<input class="input mono" type="text" wire:model.live.debounce.500ms="sitesPath" placeholder="C:\Sites">
		</label>
		<label class="set stack">
			<span class="txt"><b>{{ __('Sessies') }}</b><small style="color:{{ $folders['sessions'] ? 'var(--green-dot)' : 'var(--red)' }}">{{ $folders['sessions'] ? trans_choice(':count sessie gevonden|:count sessies gevonden', $counts['sessions'], ['count' => $counts['sessions']]) : __('Deze map bestaat niet.') }}</small></span>
			<input class="input mono" type="text" wire:model.live.debounce.500ms="sessionsPath" placeholder="~\.claude\projects">
		</label>
	</div>
</section>
