<div>
	@if ($isTooLarge)
		<button class="dfile-large" type="button" wire:click="loadAnyway">{{ __('Deze diff is te groot of bevat regels die te lang zijn om snel te tonen. Toch laden') }}</button>
	@else
		<x-diff :hunks="$hunks" :file="$file" :expandable="true" :empty="__('In dit overzicht is aan dit bestand niets veranderd.')"/>
	@endif
</div>
