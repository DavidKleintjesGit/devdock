@if (! $claudeAvailable)
	<div class="empty">
		<span class="bubble"><x-icon name="alert"/></span>
		<b>{{ __('Claude Code niet gevonden') }}</b>
		<span>{{ __('Zet het pad naar Claude Code in de instellingen.') }}</span>
	</div>
@else
	<div class="field">
		<label class="eyebrow" for="prompt-session">{{ __('Waar gaat dit heen') }}</label>
		<select id="prompt-session" class="input" wire:model="promptSession">
			<option value="">{{ __('Nieuwe sessie in :project', ['project' => $card->project ?? __('deze map')]) }}</option>
			@foreach ($card->sessions as $session)
				<option value="{{ $session->uuid }}">{{ __('Verder in :name', ['name' => $session->name()]) }} · {{ __(':count prompts', ['count' => $session->promptCount]) }}</option>
			@endforeach
		</select>
	</div>

	<div class="field">
		<label class="eyebrow" for="prompt-text">{{ __('Prompt') }}</label>
		@if ($reviewComments->isNotEmpty())
			<div style="display:flex;gap:8px;margin-bottom:8px">
				<button class="btn sm" type="button" wire:click="fillPromptFromReview">
					<span class="dot red"></span>{{ __('Prompt uit review · :count', ['count' => $reviewComments->count()]) }}
				</button>
			</div>
		@endif
		<textarea id="prompt-text" class="input" rows="6" wire:model="prompt" placeholder="{{ __('Wat moet Claude doen?') }}" @disabled($promptRun?->isRunning())></textarea>
		@error('prompt')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
	</div>

	<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
		<button class="btn primary" type="button" wire:click="send" @disabled($promptRun?->isRunning())>
			<x-icon name="chev" small/>{{ $promptRun?->isRunning() ? __('Bezig...') : __('Versturen') }}
		</button>
		@if ($card->branch)
			<span class="muted" style="font-size:12.5px">{{ __('Draait in :project op :branch', ['project' => $card->project, 'branch' => $card->branch]) }}</span>
		@endif
	</div>

	@if ($promptRun)
		<div class="field" @if ($promptRun->isRunning()) wire:poll.3s @endif>
			<h3 class="eyebrow">{{ __('Laatste ronde') }}</h3>

			<dl class="facts">
				<div><dt>{{ __('Status') }}</dt><dd>
					@if ($promptRun->isRunning())
						<span class="chip amber"><span class="dot amber pulse"></span> {{ __('Bezig') }}</span><small>{{ $promptRun->seconds() }}s</small>
					@elseif ($promptRun->hasFailed())
						<span class="chip red">{{ __('Mislukt') }}</span><small>{{ $promptRun->message }}</small>
					@else
						<span class="chip green">{{ __('Klaar') }}</span><small>{{ $promptRun->seconds() }}s{{ $promptRun->costLabel() ? ' · ' . $promptRun->costLabel() : '' }}</small>
					@endif
				</dd></div>
				<div><dt>{{ __('Sessie') }}</dt><dd class="mono">{{ $promptRun->sessionUuid ?? __('nog onbekend') }}</dd></div>
			</dl>

			<div class="code light mono">{{ $promptRun->prompt }}</div>

			@if ($promptRun->output)
				<div class="md"><p class="md-p">{{ $promptRun->output }}</p></div>
			@endif
		</div>
	@endif
@endif
