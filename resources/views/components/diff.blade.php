@props(['hunks', 'empty' => null, 'file' => null, 'expandable' => false])

<div class="diff" @if ($file) data-highlight="{{ $file }}" @endif>
	<div class="diff-rows">
	@forelse ($hunks as $hunk)
		@if ($expandable && $hunk->hiddenBefore > 0)
			<button class="hunk expand" type="button" wire:click="expand($event.shiftKey)" title="{{ __('Shift + klik toont het hele bestand') }}">
				<span class="arrow">{{ $loop->first ? '↑' : '↕' }}</span><span>{{ trans_choice('Klik om :count verborgen regel te tonen|Klik om :count verborgen regels te tonen', $hunk->hiddenBefore, ['count' => $hunk->hiddenBefore]) }} · {{ $hunk->header }}</span>
			</button>
		@else
			<div class="hunk">{{ $hunk->header }}</div>
		@endif
		@foreach ($hunk->lines as $line)
			<div class="dl {{ $line->kind }}"><span class="no">{{ $line->oldNumber }}</span><span class="no">{{ $line->newNumber }}</span><span class="sign">{{ $line->sign() }}</span><span class="code" @if ($line->markStart !== null) data-mark-start="{{ $line->markStart }}" data-mark-end="{{ $line->markEnd }}" @endif>{{ $line->text }}</span></div>
		@endforeach
		@if ($expandable && $loop->last)
			<button class="hunk expand" type="button" wire:click="expand($event.shiftKey)" title="{{ __('Shift + klik toont het hele bestand') }}">
				<span class="arrow">↓</span><span>{{ __('Klik om meer regels te tonen') }}</span>
			</button>
		@endif
	@empty
		<div class="hunk">{{ $empty ?? __('Geen verschil.') }}</div>
	@endforelse
	</div>
</div>
