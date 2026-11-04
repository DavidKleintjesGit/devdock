<header>
	<h3>{{ __('Koppelingen') }}</h3>
	<p>{{ __('Waar DevDock tickets, pull requests en sessies vandaan haalt, en hoe vaak.') }}</p>
</header>

<section>
	<h4>{{ __('Atlassian-account') }}</h4>
	<div class="setlist">
		<label class="set">
			<span class="txt"><b>{{ __('E-mailadres') }}</b><small>{{ __('De gebruikersnaam bij beide tokens.') }}</small></span>
			<span class="ctrl"><input class="input" type="email" wire:model="atlassianEmail" placeholder="{{ __('naam@bedrijf.nl') }}" autocomplete="off"></span>
		</label>
	</div>
</section>

<section>
	<h4>{{ __('Jira') }}</h4>
	<div class="setlist">
		<label class="set">
			<span class="txt"><b>{{ __('Adres') }}</b></span>
			<span class="ctrl"><input class="input mono" type="url" wire:model="jiraBaseUrl" placeholder="https://jouwbedrijf.atlassian.net" autocomplete="off"></span>
		</label>
		<label class="set">
			<span class="txt"><b>{{ __('API-token') }}</b><small>{{ $hasJiraToken ? trans_choice(':count ticket opgehaald|:count tickets opgehaald', $counts['tickets'], ['count' => $counts['tickets']]) : __('nog niet ingevuld') }}</small></span>
			<span class="ctrl"><input class="input mono" type="password" wire:model="jiraToken" placeholder="{{ $hasJiraToken ? '••••••••••••' : '' }}" autocomplete="off"></span>
		</label>
	</div>
</section>

<section>
	<h4>{{ __('Bitbucket') }}</h4>
	<div class="setlist">
		<label class="set">
			<span class="txt"><b>{{ __('Workspace') }}</b></span>
			<span class="ctrl"><input class="input mono" type="text" wire:model="bitbucketWorkspace" placeholder="workspace" autocomplete="off"></span>
		</label>
		<label class="set">
			<span class="txt"><b>{{ __('Jouw naam daar') }}</b><small>{{ __('Bepaalt welke pull requests van jou zijn.') }}</small></span>
			<span class="ctrl"><input class="input" type="text" wire:model="bitbucketMe" placeholder="{{ __('Je naam in Bitbucket') }}" autocomplete="off"></span>
		</label>
		<label class="set">
			<span class="txt"><b>{{ __('API-token') }}</b><small>{{ $hasBitbucketToken ? trans_choice(':count pull request opgehaald|:count pull requests opgehaald', $counts['pullRequests'], ['count' => $counts['pullRequests']]) : __('nog niet ingevuld') }}</small></span>
			<span class="ctrl"><input class="input mono" type="password" wire:model="bitbucketToken" placeholder="{{ $hasBitbucketToken ? '••••••••••••' : '' }}" autocomplete="off"></span>
		</label>
	</div>
</section>

<section>
	<h4>{{ __('Claude Code') }}</h4>
	<div class="setlist">
		<label class="set">
			<span class="txt"><b>{{ __('Langlopend token') }}</b><small>{{ __('Leeg: prompts draaien op de sessie waarmee je bent ingelogd.') }}</small></span>
			<span class="ctrl"><input class="input mono" type="password" wire:model="claudeToken" placeholder="{{ $hasClaudeToken ? '••••••••••••' : 'sk-ant-oat01-...' }}" autocomplete="off"></span>
		</label>
	</div>
</section>

<section @if ($run?->isRunning()) wire:poll.2s @endif>
	<h4>{{ __('Ophalen') }}</h4>
	<div class="setlist">
		<div class="set">
			<span class="txt"><b>{{ __('Automatisch ophalen') }}</b><small>{{ __('Bitbucket rust daartussen :minutes minuten; het staat maar zoveel verzoeken per uur toe.', ['minutes' => $bitbucketRestMinutes]) }}</small></span>
			<span class="ctrl">
				<span class="seg" role="group" aria-label="{{ __('Automatisch ophalen') }}">
					@foreach ([0 => __('Uit, alleen handmatig'), 60 => __('Elke minuut'), 300 => __('Elke 5 minuten'), 900 => __('Elke 15 minuten')] as $seconds => $label)
						<button type="button" aria-pressed="{{ $refreshSeconds === $seconds ? 'true' : 'false' }}" wire:click="$set('refreshSeconds', {{ $seconds }})">{{ $label }}</button>
					@endforeach
				</span>
			</span>
		</div>
		<div class="set">
			<span class="txt">
				<b>{{ __('Nu ophalen') }}</b>
				@if ($run?->isRunning())
					<small><span class="dot amber pulse"></span> {{ $run->source ?? '…' }} · {{ $run->seconds() }} s</small>
				@elseif ($run)
					<small>{{ __('Laatst om :time, in :seconds seconden.', ['time' => $run->finishedAt?->toDisplayZone()->format('H:i'), 'seconds' => $run->seconds()]) }} {{ $run->resultLine() }}</small>
					@if ($run->message)<small style="color:var(--red)">{{ $run->message }}</small>@endif
				@endif
			</span>
			<span class="ctrl"><x-busy-button action="fetchNow" class="btn sm" icon="refresh" :busy="__('Starten...')" :disabled="$run?->isRunning()">{{ __('Nu ophalen') }}</x-busy-button></span>
		</div>
	</div>
</section>
