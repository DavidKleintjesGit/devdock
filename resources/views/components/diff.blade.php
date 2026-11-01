@props(['hunks', 'empty' => null])

<div class="diff">
	@forelse ($hunks as $hunk)
		<div class="hunk">{{ $hunk->header }}</div>
		@foreach ($hunk->lines as $line)
			<div class="dl {{ $line->kind }}"><span class="no">{{ $line->oldNumber }}</span><span class="no">{{ $line->newNumber }}</span><span class="sign">{{ $line->sign() }}</span><span>{{ $line->text }}</span></div>
		@endforeach
	@empty
		<div class="hunk">{{ $empty ?? __('Geen verschil.') }}</div>
	@endforelse
</div>
