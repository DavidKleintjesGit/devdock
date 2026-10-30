@props(['title', 'summary' => null, 'open' => false])

{{--
	Een veld dat je dichtklapt. Het is bewust hetzelfde veld als overal elders
	in de lade: dezelfde kop, dezelfde hint ernaast. Alleen de kop is nu een
	knop, met een teken dat zegt of er iets onder zit.

	De stand staat in Alpine: Livewire vervangt de lade bij elke klik, en een
	stand die van de server komt zou dan steeds terugvallen.
--}}
<div class="field" x-data="{ open: @js($open) }">
	<button class="fieldhead foldhead" type="button" :aria-expanded="open" @click="open = ! open">
		<h3 class="eyebrow"><span class="caret" aria-hidden="true" x-text="open ? '−' : '+'">+</span>{{ $title }}</h3>
		<span class="muted" style="font-size:12.5px">{{ $summary }}</span>
	</button>
	<div class="foldbody" x-show="open" x-cloak>
		{{ $slot }}
	</div>
</div>
