@props(['hunks', 'empty' => null, 'file' => null])

<div class="diff" @if ($file) data-highlight="{{ $file }}" @endif>
	@forelse ($hunks as $hunk)
		<div class="hunk">{{ $hunk->header }}</div>
		@foreach ($hunk->lines as $line)
			<div class="dl {{ $line->kind }}"><span class="no">{{ $line->oldNumber }}</span><span class="no">{{ $line->newNumber }}</span><span class="sign">{{ $line->sign() }}</span><span class="code">{{ $line->text }}</span></div>
		@endforeach
	@empty
		<div class="hunk">{{ $empty ?? __('Geen verschil.') }}</div>
	@endforelse
</div>
