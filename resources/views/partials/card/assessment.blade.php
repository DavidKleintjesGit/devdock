<section class="assessment" aria-label="{{ __('Beoordeling ronde :number', ['number' => $roundAssessment->roundNumber]) }}">
	<header class="assessment-head">
		<span class="sdot amber"></span>
		<b>{{ __('Beoordeling ronde :number · jij kiest', ['number' => $roundAssessment->roundNumber]) }}</b>
		<span class="hint push-end">{{ $choiceSummary }}</span>
	</header>
	<ol class="assessment-list">
		@foreach ($roundAssessment->findings as $assessed)
			<li @class(['picked' => ($findingChoices[$assessed->finding->reviewFindingId->id] ?? $assessed->suggestedChoice()->value) === \Src\Review\Enums\FindingChoiceEnum::FIX->value]) wire:key="assessed-{{ $assessed->finding->reviewFindingId->id }}">
				<span class="assessment-line">
					<b>{{ $assessed->finding->lensName }}</b>
					@if ($assessed->finding->location)<span class="mono hint assessment-where">{{ $assessed->finding->location }}</span>@else<span class="assessment-where"></span>@endif
					<span class="seg sm" role="group" aria-label="{{ __('Wat doe je hiermee') }}">
						@foreach (\Src\Review\Enums\FindingChoiceEnum::cases() as $choice)
							<button type="button" aria-pressed="{{ ($findingChoices[$assessed->finding->reviewFindingId->id] ?? $assessed->suggestedChoice()->value) === $choice->value ? 'true' : 'false' }}" wire:click="chooseFinding({{ $assessed->finding->reviewFindingId->id }}, '{{ $choice->value }}')">{{ $choice->label() }}</button>
						@endforeach
					</span>
				</span>
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
			</li>
		@endforeach
	</ol>
	@if ($showsChatLink ?? false)
		<footer class="assessment-foot">
			<span class="hint">{{ __('Wat je kiest wordt de opdracht in de chat; na de fix start één nieuwe ronde.') }}</span>
			<button class="btn sm primary push-end" type="button" wire:click="toChatWithChoices">{{ __('Naar de chat') }}</button>
		</footer>
	@else
		<footer class="assessment-foot">
			<span class="hint">{{ __('Je keuzes staan als opdracht in het tekstvak hieronder; je kunt er nog in typen.') }}</span>
		</footer>
	@endif
</section>
