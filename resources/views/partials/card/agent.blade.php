@if (! $claudeAvailable)
	<div class="empty">
		<span class="bubble"><x-icon name="alert"/></span>
		<b>{{ __('Claude Code niet gevonden') }}</b>
		<span>{{ __('Zet het pad naar Claude Code in de instellingen.') }}</span>
	</div>
@else
	@if ($card->agent)
		<div class="agentbar">
			<span class="dot green"></span>
			<span class="grow">
				<b>{{ $card->agent->name }}</b>
				<small class="mono">{{ $card->agent->shortKey() }}{{ $card->agent->directory ? ' · ' . $card->agent->directory : '' }}</small>
			</span>
			<x-busy-button action="openInClaude" class="btn sm" :busy="__('Openen...')">{{ __('Open in Claude') }}</x-busy-button>
			<x-busy-button action="replaceAgent" class="btn sm" :busy="__('Bezig...')">{{ __('Vervangen') }}</x-busy-button>
		</div>
	@endif

	@if ($conversation)
		<div class="talk">
			@foreach ($conversation as $exchange)
				<div class="say mine">
					<span class="eyebrow">{{ __('Jij') }}</span>
					<div class="said">{{ $exchange->run->prompt }}</div>
				</div>

				<div class="say theirs">
					<div class="fieldhead">
						<span class="eyebrow">{{ $card->agent?->name ?? __('Agent') }}</span>
						@if ($exchange->run->isRunning())
							<span class="chip amber"><span class="dot amber pulse"></span>{{ __('Bezig') }}</span>
						@elseif ($exchange->run->hasFailed())
							<span class="chip red">{{ __('Mislukt') }}</span>
						@elseif ($exchange->report)
							<span class="chip {{ $exchange->report->status->tone() }}">{{ $exchange->report->status->label() }}</span>
						@endif
						<span class="right muted" style="font-size:12px">{{ $exchange->run->seconds() }}s{{ $exchange->run->costLabel() ? ' · ' . $exchange->run->costLabel() : '' }}</span>
					</div>

					@if ($exchange->run->hasFailed())
						<span class="note red"><x-icon name="alert" small/><span>{{ $exchange->run->message }}</span></span>
					@elseif ($exchange->body || $exchange->report)
						<div class="said">
							@if ($exchange->body)
								<div class="md">{!! $exchange->body !!}</div>
							@endif

							@if ($exchange->report)
								<p class="lede">{{ $exchange->report->summary }}</p>
							@endif

							<div class="outcome">
								@if ($exchange->report?->done)
									<div>
										<h4 class="eyebrow">{{ __('Gedaan') }}</h4>
										@foreach ($exchange->report?->done ?? [] as $line)
											<span class="bullet"><x-icon name="check" small/><span>{{ $line }}</span></span>
										@endforeach
									</div>
								@endif
								@if ($exchange->report?->open)
									<div>
										<h4 class="eyebrow">{{ __('Nog open') }}</h4>
										@foreach ($exchange->report?->open ?? [] as $line)
											<span class="bullet"><x-icon name="alert" small/><span>{{ $line }}</span></span>
										@endforeach
									</div>
								@endif
							</div>

							@if ($exchange->report?->question)
								<span class="note amber"><x-icon name="info" small/><span>{{ $exchange->report->question }}</span></span>
							@endif

						</div>
					@endif

				</div>
			@endforeach
		</div>
	@endif

	@if ($agents && $agents->count() > 1)
		<div class="field">
			<h3 class="eyebrow">{{ __('Eerdere agents') }}</h3>
			<div class="linklist">
				@foreach ($agents as $agent)
					<div class="linkrow static">
						<span class="dot {{ $agent->isActive ? 'green' : '' }}"></span>
						<span class="grow">
							<b>{{ $agent->name }}</b>
							<small class="mono">{{ $agent->shortKey() }} · {{ $agent->directory ?? __('zonder map') }}</small>
						</span>
						@if ($agent->isActive)
							<span class="chip green">{{ __('actief') }}</span>
						@else
							<button class="btn sm" type="button" wire:click="useAgent('{{ $agent->sessionUuid }}')">{{ __('Op actief zetten') }}</button>
						@endif
					</div>
				@endforeach
			</div>
		</div>
	@endif

	@if ($card->project === null || $card->branch === null)
		<div class="box">
			<div class="empty">
				<span class="bubble"><x-icon name="lock"/></span>
				<b>{{ __('Nog niet klaar om te beginnen') }}</b>
				<span>{{ __('Er ontbreekt nog iets voordat een agent aan deze taak mag werken.') }}</span>
				<button class="btn primary" type="button" wire:click="setDrawerTab('Workflow')">{{ __('Naar de workflow') }}</button>
			</div>
		</div>

		<div class="dfoot">
			<button class="btn primary" type="button" wire:click="setDrawerTab('Workflow')">{{ __('Naar de workflow') }}</button>
			<span class="hint">{{ __('geblokkeerd') }}</span>
		</div>
	@else
		<div class="dfoot stack" @if ($run?->isRunning()) wire:poll.3s @endif>
			@if ($promptSources !== [])
				<span class="sources">
					@foreach ($promptSources as $source)
						<button class="btn sm" type="button" wire:click="fillPromptFrom('{{ $source['case']->value }}')" wire:target="fillPromptFrom('{{ $source['case']->value }}')" wire:loading.attr="disabled">
							<span class="dot {{ $source['case']->tone() }}"></span>{{ $source['case']->label() }}@if ($source['count'])<span class="n">{{ $source['count'] }}</span>@endif
						</button>
					@endforeach
				</span>
			@endif
			<label class="sr" for="prompt-text">{{ $card->agent ? __('Volgende stap') : __('Eerste prompt') }}</label>
			<div class="composer">
				<textarea id="prompt-text" class="textarea" data-send-on-enter wire:model="prompt" placeholder="{{ $card->agent ? __('Wat is de volgende stap?') : __('Wat moet Claude doen?') }}" @disabled($run?->isRunning())></textarea>
				<button class="send" type="button" wire:click="send" wire:target="send" wire:loading.attr="disabled"
					aria-label="{{ $card->agent ? __('Versturen') : __('Gesprek beginnen') }}" @disabled($run?->isRunning())>
						<span wire:loading.remove wire:target="send"><x-icon name="chev" small/></span>
					<span wire:loading wire:target="send" class="dot amber pulse"></span>
				</button>
				@if ($run?->isRunning())
					<button class="stop" type="button" wire:click="stopPrompt" aria-label="{{ __('Afbreken') }}"><x-icon name="x" small/></button>
				@endif
			</div>
			@error('prompt')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
			<div class="sendrow">
				<span class="hint">{{ $run?->isRunning() ? __('Bezig...') : ($card->agent ? __('Enter verstuurt, Shift+Enter is een nieuwe regel') : __('Hiermee krijgt deze taak zijn agent')) }}</span>
				<span class="hint mono">{{ $card->branch ?? $card->project }}</span>
			</div>
		</div>
	@endif
@endif