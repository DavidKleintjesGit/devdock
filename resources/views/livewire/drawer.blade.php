<div class="drawer-host">
	@if ($drawer)
		<div class="scrim" wire:click="closeDrawer"></div>

		@if ($drawer['type'] === 'session')
			@include('partials.session-drawer', $drawer)
		@elseif ($drawer['type'] === 'finding')
			@include('partials.finding-drawer', $drawer)
		@else
			@include('partials.card-drawer', $drawer)
		@endif
	@endif

	@if ($drawer && $drawer['type'] === 'card' && $drawer['openDiffFile'])
		@include('partials.diff-panel', $drawer)
	@endif

	@if ($drawer && $drawer['type'] === 'session' && $renaming)
		@include('partials.session-rename', ['session' => $drawer['session']])
	@endif
</div>
