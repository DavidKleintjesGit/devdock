@props(['tones' => []])

{{-- The dots beside a tab that say something needs you, one per colour, pushed half over each other so two take little more room than one. --}}
@if ($tones !== [])
	<span {{ $attributes->class(['hint-dots']) }} title="{{ collect($tones)->map(fn (\Src\Board\Enums\HintToneEnum $hintTone): string => $hintTone->label())->implode(' · ') }}">
		@foreach ($tones as $hintTone)
			<span class="hint-dot {{ $hintTone->value }}"></span>
		@endforeach
	</span>
@endif
