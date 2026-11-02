<div class="dbody" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight); setTimeout(() => $el.scrollTop = $el.scrollHeight, 400)" @if ($run?->isRunning()) wire:poll.1500ms="followProgress" @endif>
	@if (! $claudeAvailable)
		<div class="dsec">
			<div class="empty">
				<b>{{ __('Claude Code niet gevonden') }}</b>
				<span>{{ __('Zet het pad naar Claude Code in de instellingen.') }}</span>
				<button class="btn sm" type="button" onclick="Livewire.dispatch('open-settings', { section: 'koppelingen' })">{{ __('Naar de instellingen') }}</button>
			</div>
		</div>
	@elseif ($card->project === null || $card->branch === null)
		<div class="dsec">
			<div class="empty">
				<b>{{ __('Nog niet klaar om te beginnen') }}</b>
				<span>{{ __('Zonder project en branch weet de agent niet waar hij moet werken.') }}</span>
				<button class="btn sm" type="button" wire:click="setDrawerTab('Koppelingen')">{{ __('Naar de koppelingen') }}</button>
			</div>
		</div>
	@else
		<div class="banner gray chat-banner">
			<span>{{ __('Krijgt mee') }}</span>
			<b>{{ $card->ticketKey ? __('het ticket en de werkwijze van :project', ['project' => $card->project]) : __('de werkwijze van :project', ['project' => $card->project]) }}</b>
			@if ($card->agent)<span class="mono agent-name">{{ $card->agent->name }}</span>@endif
		</div>

		@if ($conversation)
			<div class="chat">
				@foreach ($conversation as $exchange)
					<div class="chat-turn you" wire:key="turn-you-{{ $exchange->run->promptRunId->id }}" x-data="{ folded: @js($exchange->run->hasLongPrompt()) }">
						<div class="bubble" :class="{ folded }" title="{{ $exchange->run->startedAt?->calendar() }}">{{ $exchange->run->prompt }}</div>
						@if ($exchange->run->hasLongPrompt())
							<button class="btn text xs unfold" type="button" @click="folded = ! folded" x-text="folded ? @js(__('Hele bericht tonen')) : @js(__('Inklappen'))">{{ __('Hele bericht tonen') }}</button>
						@endif
					</div>

					<div class="chat-turn agent" wire:key="turn-agent-{{ $exchange->run->promptRunId->id }}">
						<span class="who">
							@if ($exchange->run->isRunning())
								<span class="sdot busy"></span>
								{{ $card->agent?->name ?? __('agent') }} · <span class="ink">{{ __('bezig sinds :time', ['time' => $exchange->run->startedAt?->format('H:i')]) }}</span>
								<button class="btn xs stop" type="button" wire:click="stopPrompt">{{ __('Stoppen') }}</button>
							@else
								{{ $card->agent?->name ?? __('agent') }} · {{ __(':count s', ['count' => $exchange->run->seconds()]) }}{{ $exchange->run->costLabel() ? ' · ' . $exchange->run->costLabel() : '' }}
							@endif
						</span>

						@if ($exchange->transcript && $exchange->transcript->steps !== [])
							<details class="agent-steps">
								<summary>
									<x-icon name="chev" small/>
									<span class="grow">
										{{ trans_choice(':count stap|:count stappen', count($exchange->transcript->steps), ['count' => count($exchange->transcript->steps)]) }}
										@if ($exchange->run->isRunning())
											· {{ __('nu') }}: {{ $exchange->transcript->latestStep()->tool }} <span class="mono">{{ $exchange->transcript->latestStep()->subject }}</span>
										@elseif ($exchange->transcript->summary() !== '')
											· {{ $exchange->transcript->summary() }}
										@endif
									</span>
								</summary>
								<ol class="step-list">
									@foreach ($exchange->transcript->steps as $step)
										<li><span class="tool">{{ $step->tool }}</span><span class="mono subject">{{ $step->subject }}</span></li>
									@endforeach
								</ol>
							</details>
						@endif

						@if ($exchange->run->hasFailed())
							<div class="alert red">{{ $exchange->run->message }}</div>
						@endif

						<div class="answer">
							@foreach ($exchange->passages as $passage)
								@if ($passage instanceof \Src\Prompt\Objects\MockupObject)
									<x-mockup :mockup="$passage" :run="$exchange->run->promptRunId->id" wire:key="mockup-{{ $exchange->run->promptRunId->id }}-{{ $loop->index }}"/>
								@else
									{!! $passage !!}
								@endif
							@endforeach
							@if ($exchange->run->isRunning())
								@if ($exchange->passages === [])<p class="md-p muted">{{ __('Aan het werk…') }}</p>@endif
								<span class="caret" aria-hidden="true"></span>
							@endif
						</div>

						@if (! $exchange->run->isRunning() && ($exchange->report || ($loop->last && $exchange->choices !== [])))
							<div class="reportbar {{ $exchange->report?->status->tone() ?? 'amber' }}">
								<span class="state"><span class="sdot"></span>{{ $exchange->report?->status->label() ?? __('Vraag') }}</span>
								@if ($exchange->report?->question)
									<p class="question">{{ $exchange->report->question }}</p>
								@elseif ($exchange->report && $exchange->report->open !== [])
									<ul class="open">
										@foreach ($exchange->report->open as $openPoint)<li>{{ $openPoint }}</li>@endforeach
									</ul>
								@endif
								<div class="reportbar-actions">
									@if ($loop->last && $exchange->choices !== [])
										@foreach ($exchange->choices as $choice)
											<button class="btn sm {{ $loop->first ? 'primary' : '' }}" type="button" wire:click="answer(@js($choice))" wire:key="choice-{{ $loop->index }}">{{ $choice }}</button>
										@endforeach
									@endif
									<button class="btn sm push-end" type="button" wire:click="setDrawerTab('Werkkopie')">{{ __('Wijzigingen bekijken') }}</button>
								</div>
							</div>
						@endif
					</div>
				@endforeach
			</div>
		@else
			<div class="dsec">
				<p class="muted">{{ $card->agent ? __(':name wacht op een opdracht.', ['name' => $card->agent->name]) : __('Je eerste bericht geeft deze taak zijn agent.') }}</p>
			</div>
		@endif

		@if ($agents && $agents->count() > 1)
			<section class="dsec line">
				<h3 class="eyebrow">{{ __('Eerdere agents') }}</h3>
				<div class="list">
					@foreach ($agents as $agent)
						<div class="row">
							<span class="sdot {{ $agent->isActive ? 'yours' : 'quiet' }}"></span>
							<span class="grow title"><b>{{ $agent->name }}</b><small class="mono">{{ $agent->shortKey() }}</small></span>
							@if ($agent->isActive)
								<span class="label teal">{{ __('actief') }}</span>
							@else
								<button class="btn xs" type="button" wire:click="useAgent('{{ $agent->sessionUuid }}')">{{ __('Op actief zetten') }}</button>
							@endif
						</div>
					@endforeach
				</div>
			</section>
		@endif
	@endif
</div>

@if ($claudeAvailable && $card->project !== null && $card->branch !== null)
	<div class="composer">
		@if ($promptSources !== [])
			@if ($composing)
				<div class="sources">
					<div style="display:flex;align-items:center;gap:10px">
						<b class="grow" style="font-size:13px">{{ __('Prompt maken uit context') }}</b>
						<button class="btn text xs" type="button" wire:click="toggleComposing">{{ __('Inklappen') }}</button>
					</div>
					<div class="source-chips" role="group" aria-label="{{ __('Context') }}">
						@foreach ($promptSources as $source)
							<button class="source-chip" type="button" role="checkbox" aria-checked="{{ in_array($source['case']->value, $pickedSources, true) ? 'true' : 'false' }}" wire:click="togglePromptSource('{{ $source['case']->value }}')" wire:key="source-{{ $source['case']->value }}">
								<span class="mark"><x-icon name="check" small/></span>{{ $source['label'] }}
							</button>
						@endforeach
					</div>
					<div style="display:flex;align-items:center;gap:10px">
						<span class="hint grow">{{ __('De prompt komt in het veld hieronder. Je leest hem eerst.') }}</span>
						<button class="btn sm primary" type="button" wire:click="buildPrompt" @disabled($pickedSourceCount === 0)><x-icon name="skills" small/>{{ __('Prompt maken') }}@if ($pickedSourceCount > 0) · {{ $pickedSourceCount }}@endif</button>
					</div>
				</div>
			@endif
		@endif

		<div class="composer-row">
			@if ($promptSources !== [] && ! $composing)
				<button class="btn text sm" type="button" wire:click="toggleComposing">
					<x-icon name="plus" small/>{{ __('Prompt maken uit context') }}
					<span class="muted" style="font-weight:400">· {{ $promptSourceSummary }}</span>
				</button>
			@endif
			<span class="hint">{{ __('Enter stuurt · Shift+Enter is een nieuwe regel') }}</span>
		</div>

		<div class="boxwrap">
			<label class="sr" for="prompt-text">{{ __('Bericht aan de agent') }}</label>
			<textarea id="prompt-text" class="textarea" data-send-on-enter wire:model="prompt" placeholder="{{ __('Wat is de volgende stap?') }}" @disabled($run?->isRunning())></textarea>
			<button class="btn primary send" type="button" wire:click="send" wire:target="send" wire:loading.attr="disabled" aria-label="{{ __('Versturen') }}" @disabled($run?->isRunning())>
				<span wire:loading.remove wire:target="send"><x-icon name="send" small/></span>
				<span wire:loading wire:target="send" class="dot amber pulse"></span>
			</button>
		</div>
		@error('prompt')<span class="field"><span class="err">{{ $message }}</span></span>@enderror
	</div>
@endif
