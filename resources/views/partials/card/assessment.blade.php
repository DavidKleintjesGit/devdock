<section class="assessment" aria-label="{{ __('Beoordeling ronde :number', ['number' => $roundAssessment->roundNumber]) }}">
	<details class="assessment-block" open wire:ignore.self wire:key="assessment-{{ $roundAssessment->reviewRoundId->id }}">
		<summary class="assessment-head">
			<x-icon name="chev" small/>
			<span class="sdot amber"></span>
			<b>{{ $roundAssessment->heading() }}</b>
			<span class="hint push-end">{{ $choiceSummary }}</span>
		</summary>
		<ol class="assessment-list">
			@foreach ($roundAssessment->findings as $assessed)
				<li @class(['picked' => ($findingChoices[$assessed->finding->reviewFindingId->id] ?? $assessed->suggestedChoice()->value) === \Src\Review\Enums\FindingChoiceEnum::FIX->value]) wire:key="assessed-{{ $assessed->finding->reviewFindingId->id }}">
					<details class="assessment-finding" open wire:ignore.self>
						<summary class="assessment-line">
							<x-icon name="chev" small/>
							<b>{{ $assessed->finding->lensName }}</b>
							@if ($assessed->finding->location)<span class="mono hint assessment-where">{{ $assessed->finding->location }}</span>@else<span class="assessment-where"></span>@endif
							{{-- a choice is made without folding the finding open or shut --}}
							<span class="seg sm" role="group" aria-label="{{ __('Wat doe je hiermee') }}" x-on:click.prevent>
								@foreach (\Src\Review\Enums\FindingChoiceEnum::cases() as $choice)
									<button type="button" aria-pressed="{{ ($findingChoices[$assessed->finding->reviewFindingId->id] ?? $assessed->suggestedChoice()->value) === $choice->value ? 'true' : 'false' }}" wire:click="chooseFinding({{ $assessed->finding->reviewFindingId->id }}, '{{ $choice->value }}')">{{ $choice->label() }}</button>
								@endforeach
							</span>
						</summary>
						<div class="assessment-body">
							<p>{{ $assessed->finding->text }}</p>
							@if ($assessed->assessment)
								<p class="assessment-agent"><span class="label {{ $assessed->assessment->verdict === \Src\Review\Enums\AssessmentVerdictEnum::AGREE ? 'teal' : 'gray' }}">{{ $assessed->assessment->verdict->label() }}</span>{{ $assessed->assessment->proposal }}</p>
							@endif
							@if (($findingChoices[$assessed->finding->reviewFindingId->id] ?? $assessed->suggestedChoice()->value) === \Src\Review\Enums\FindingChoiceEnum::IGNORE->value)
								<label class="assessment-reason">
									<span>{{ __('Waarom laten liggen') }}</span>
									<input class="input" type="text" wire:model.live.debounce.500ms="ignoreReasons.{{ $assessed->finding->reviewFindingId->id }}" autocomplete="off">
								</label>
							@endif
						</div>
					</details>
				</li>
			@endforeach
		</ol>
	</details>
	<details class="assessment-message" wire:ignore.self>
		<summary>
			<x-icon name="chev" small/>
			<span>{{ __('Bericht aan de agent aanpassen') }}</span>
			@if ($isFixMessageOwn)<span class="label gray">{{ __('zelf aangepast') }}</span>@endif
		</summary>
		<textarea class="textarea" rows="8" wire:model.live.debounce.500ms="fixMessage" aria-label="{{ __('Bericht aan de agent') }}"></textarea>
		@if ($isFixMessageOwn)
			<button class="btn sm" type="button" wire:click="rebuildFixMessage">{{ __('Opnieuw opbouwen uit je keuzes') }}</button>
		@endif
	</details>
	<footer class="assessment-foot">
		<span class="hint">{{ __('Na de fix start één nieuwe ronde van de lenzen.') }}</span>
		<button class="btn sm primary push-end" type="button" wire:click="handFindingsToAgent">{{ __('Geef aan de agent') }}</button>
	</footer>
</section>
