<div class="dbody workspace setup-workspace">
	@if ($readiness)
		<x-panel :padded="false">
			@include('partials.card.gereed-lijst')
		</x-panel>
	@else
		<p class="hint">{{ __('Deze werkkopie is nog niet nagekeken.') }}</p>
	@endif
</div>
