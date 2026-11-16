@props(['tones' => []])

{{-- One dot beside a tab that says something needs you. With more than one tone it is one dot split between the colours, not a row of dots. --}}
@if ($tones !== [])
	<span
		{{ $attributes->class(['hint-dot', $tones[0]->value => count($tones) === 1]) }}
		title="{{ collect($tones)->map(fn (\Src\Board\Enums\HintToneEnum $hintTone): string => $hintTone->label())->implode(' · ') }}"
		@if (count($tones) > 1) style="background:conic-gradient({{ collect($tones)->values()->map(fn (\Src\Board\Enums\HintToneEnum $hintTone, int $index): string => 'var(--hint-' . $hintTone->value . ') ' . ($index * 360 / count($tones)) . 'deg ' . (($index + 1) * 360 / count($tones)) . 'deg')->implode(', ') }})" @endif
	></span>
@endif
