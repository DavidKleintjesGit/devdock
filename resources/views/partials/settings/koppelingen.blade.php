<div class="panelhead"><h3>{{ __('Koppelingen') }}</h3></div>

<div class="setgroup">{{ __('Atlassian-account') }}</div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="atlassian-email">{{ __('E-mailadres') }}</label>
		<span class="hint">{{ __('De gebruikersnaam bij beide tokens.') }}</span>
	</div>
	<input id="atlassian-email" class="input" type="email" wire:model="atlassianEmail" autocomplete="off">
</div>

<div class="setgroup">{{ __('Jira') }}</div>

<div class="set">
	<div class="txt"><label class="lbl" for="jira-url">{{ __('Adres') }}</label></div>
	<input id="jira-url" class="input mono" type="url" wire:model="jiraBaseUrl" autocomplete="off">
</div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="jira-token">{{ __('API-token') }}</label>
		@if ($counts['tickets'])
			<span class="hint ok"><x-icon name="check" small/>{{ __(':count tickets', ['count' => $counts['tickets']]) }}</span>
		@elseif ($hasJiraToken)
			<span class="hint ok"><x-icon name="check" small/>{{ __('Ingesteld') }}</span>
		@else
			<span class="hint">{{ __('Nog leeg') }}</span>
		@endif
	</div>
	<input id="jira-token" class="input" type="password" wire:model="jiraToken" placeholder="{{ $hasJiraToken ? '••••••••••••' : '' }}" autocomplete="off">
</div>

<div class="setgroup">{{ __('Bitbucket') }}</div>

<div class="set">
	<div class="txt"><label class="lbl" for="bitbucket-workspace">{{ __('Workspace') }}</label></div>
	<input id="bitbucket-workspace" class="input mono" type="text" wire:model="bitbucketWorkspace" autocomplete="off">
</div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="bitbucket-me">{{ __('Jouw naam daar') }}</label>
		<span class="hint">{{ __('Bepaalt wat van jou is en wat ter review ligt.') }}</span>
	</div>
	<input id="bitbucket-me" class="input" type="text" wire:model="bitbucketMe" autocomplete="off">
</div>

<div class="set">
	<div class="txt">
		<label class="lbl" for="bitbucket-token">{{ __('API-token') }}</label>
		@if ($counts['pullRequests'])
			<span class="hint ok"><x-icon name="check" small/>{{ __(':count pull requests', ['count' => $counts['pullRequests']]) }}</span>
		@elseif ($hasBitbucketToken)
			<span class="hint ok"><x-icon name="check" small/>{{ __('Ingesteld') }}</span>
		@else
			<span class="hint">{{ __('Nog leeg') }}</span>
		@endif
	</div>
	<input id="bitbucket-token" class="input" type="password" wire:model="bitbucketToken" placeholder="{{ $hasBitbucketToken ? '••••••••••••' : '' }}" autocomplete="off">
</div>
