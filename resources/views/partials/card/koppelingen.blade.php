<div class="subbar">
	<span class="grow"></span>
	<span class="hint">{{ __('Jira en Bitbucket bijgewerkt om :time', ['time' => $fetchedAt ?? __('nooit')]) }}</span>
	<x-busy-button action="fetchNow" class="btn sm" icon="refresh" :busy="__('Ophalen...')">{{ __('Nu ophalen') }}</x-busy-button>
</div>

<div class="dbody workspace" x-data="{ editing: null }">
	<div class="link-grid">
		<div class="link-card">
			<span class="link-icon"><x-icon name="file" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>{{ __('Ticket') }}</b><span class="mono" style="color:var(--fg-2)">{{ $card->ticketKey ?? __('geen') }}</span>@if ($ticket?->status)<span class="label amber">{{ $ticket->status }}</span>@endif</span>
				<span class="hint" style="font-size:13px">{{ $ticket ? __('Prioriteit :priority', ['priority' => mb_strtolower((string) $ticket->priority)]) . ($ticket->sprint ? ' · ' . $ticket->sprint : ' · ' . __('in de backlog')) : __('Zonder ticket heet alles naar de titel.') }}</span>
				@if ($card->ticketUrl)<a href="{{ $card->ticketUrl }}" target="_blank" rel="noopener" style="align-self:flex-start;font-size:13px;text-decoration:none">{{ __('Openen in Jira') }}</a>@endif
				<div x-show="editing === 'ticket'" x-cloak style="display:flex;gap:8px;margin-top:6px">
					<input class="input mono" type="text" wire:model="linkTicket" placeholder="PROJ-123" style="height:34px">
					<button class="btn sm" type="button" wire:click="link('ticket')" @click="editing = null">{{ __('Vastzetten') }}</button>
				</div>
			</div>
			<button class="btn xs" type="button" @click="editing = editing === 'ticket' ? null : 'ticket'">{{ __('Wijzigen') }}</button>
		</div>

		<div class="link-card">
			<span class="link-icon"><x-icon name="folder" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<span style="display:flex;align-items:center;gap:8px"><b>{{ __('Project') }}</b><span class="mono" style="color:var(--fg-2)">{{ $card->project ?? __('geen') }}</span></span>
				<span class="hint" style="font-size:13px">{{ __('Hier werkt de agent, in een eigen werkkopie.') }}</span>
				<div x-show="editing === 'project'" x-cloak style="display:flex;gap:8px;margin-top:6px">
					<select class="input" wire:model="linkProject" style="height:34px">
						<option value="">{{ __('Kies een project') }}</option>
						@foreach ($projectChoices as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
					</select>
					<button class="btn sm" type="button" wire:click="link('project')" @click="editing = null">{{ __('Vastzetten') }}</button>
				</div>
			</div>
			<button class="btn xs" type="button" @click="editing = editing === 'project' ? null : 'project'">{{ __('Wijzigen') }}</button>
		</div>

		<div class="link-card">
			<span class="link-icon"><x-icon name="branch" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>{{ __('Branch') }}</b><span class="mono" style="color:var(--fg-2)">{{ $card->branch ?? __('geen') }}</span></span>
				<span class="hint" style="font-size:13px">
					@if ($worktree){{ $worktree->upstream ? __('Lokaal en op origin') : __('Alleen lokaal') }}{{ $worktree->ahead ? ' · ' . trans_choice(':count commit nog niet gepusht|:count commits nog niet gepusht', $worktree->ahead, ['count' => $worktree->ahead]) : '' }}@else{{ __('Nog geen werkkopie op deze branch.') }}@endif
				</span>
				@if (count($branchRows) > 1)
					<span class="chips" style="margin-top:4px">
						@foreach ($branchRows as $row)
							@if ($row['isActive'])<span class="label teal mono">{{ $row['value'] }}</span>@else<button class="tchip mono" type="button" wire:click="activate('branch', '{{ $row['value'] }}')">{{ $row['value'] }}</button>@endif
						@endforeach
					</span>
				@endif
				<div x-show="editing === 'branch'" x-cloak style="display:flex;gap:8px;margin-top:6px">
					<select class="input mono" wire:model="linkBranch" style="height:34px">
						<option value="">{{ __('Kies een bestaande branch') }}</option>
						@foreach ($branchChoices as $branch)<option value="{{ $branch }}">{{ $branch }}</option>@endforeach
					</select>
					<button class="btn sm" type="button" wire:click="link('branch')" @click="editing = null">{{ __('Vastzetten') }}</button>
				</div>
			</div>
			<button class="btn xs" type="button" @click="editing = editing === 'branch' ? null : 'branch'">{{ __('Wijzigen') }}</button>
		</div>

		<div class="link-card">
			<span class="link-icon"><x-icon name="pr" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>{{ __('Pull request') }}</b><span class="mono" style="color:var(--fg-2)">{{ $card->activePullRequest ?? __('nog geen') }}</span>@if ($activePullRequest)<span class="label {{ $activePullRequest->isOpen() ? 'blue' : 'green' }}">{{ $activePullRequest->isOpen() ? __('Open') : __('Gemerged') }}</span>@endif</span>
				@if ($activePullRequest)
					<span class="hint" style="font-size:13px">{{ __('naar :branch', ['branch' => $activePullRequest->destinationBranch]) }} · {{ __(':approvals van :reviewers goedgekeurd', ['approvals' => $activePullRequest->approvals, 'reviewers' => $activePullRequest->reviewers]) }}{{ $activePullRequest->changesRequestedBy ? ' · ' . __(':name vraagt om wijzigingen', ['name' => $activePullRequest->changesRequestedBy]) : '' }}</span>
					<a href="{{ $activePullRequest->url }}" target="_blank" rel="noopener" style="align-self:flex-start;font-size:13px;text-decoration:none">{{ __('Openen in Bitbucket') }}</a>
				@else
					<span class="hint" style="font-size:13px">{{ __('Komt vanzelf zodra je in Bitbucket een pull request maakt van deze branch.') }}</span>
				@endif
				<div x-show="editing === 'pull_request'" x-cloak style="display:flex;gap:8px;margin-top:6px">
					<input class="input mono" type="text" wire:model="linkPullRequest" placeholder="repository#123" style="height:34px">
					<button class="btn sm" type="button" wire:click="link('pull_request')" @click="editing = null">{{ __('Vastzetten') }}</button>
				</div>
			</div>
			<button class="btn xs" type="button" @click="editing = editing === 'pull_request' ? null : 'pull_request'">{{ __('Wijzigen') }}</button>
		</div>

		<div class="link-card">
			<span class="link-icon"><x-icon name="search" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<b>{{ __('Mag ook inzien') }}</b>
				<span class="hint" style="font-size:13px">{{ __('Andere projecten waar de agent mag lezen.') }}</span>
				@if ($readableProjects !== [])
					<span class="chips" style="margin-top:4px">
						@foreach ($readableProjects as $name)<span class="tchip on">{{ $name }}<button class="icon-btn" type="button" style="width:18px;height:18px" wire:click="unlink('readable_project', '{{ $name }}')" aria-label="{{ __('Toegang weghalen') }}"><x-icon name="x" small/></button></span>@endforeach
					</span>
				@endif
				<div x-show="editing === 'readable'" x-cloak style="display:flex;gap:8px;margin-top:6px">
					<select class="input" wire:model="linkReadableProject" style="height:34px">
						<option value="">{{ __('Kies een project') }}</option>
						@foreach ($projectChoices as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
					</select>
					<button class="btn sm" type="button" wire:click="link('readable_project')" @click="editing = null">{{ __('Toegang geven') }}</button>
				</div>
			</div>
			<button class="btn xs" type="button" @click="editing = editing === 'readable' ? null : 'readable'">{{ __('Toevoegen') }}</button>
		</div>

		<div class="link-card">
			<span class="link-icon"><x-icon name="folder" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>{{ __('Werkkopie') }}</b><span style="color:var(--fg-2)">{{ $worktree ? ($worktree->waitingLabel() ?? __('schoon')) : __('nog geen eigen map') }}</span></span>
				<span class="hint" style="font-size:13px">{{ __('Eigen map naast het project, waar de agent en de lenzen werken.') }}</span>
			</div>
			<button class="btn xs" type="button" wire:click="setDrawerTab('Werkkopie')">{{ __('Naar de werkkopie') }}</button>
		</div>

		<div class="link-card">
			<span class="link-icon"><x-icon name="chat" small/></span>
			<div class="grow" style="display:flex;flex-direction:column;gap:4px">
				<span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>{{ __('Agent') }}</b><span class="mono" style="color:var(--fg-2)">{{ $card->agent?->name ?? __('nog geen') }}</span></span>
				<span class="hint" style="font-size:13px">{{ __('Werkt in deze werkkopie. Wisselen doe je in de chat.') }}</span>
			</div>
			<button class="btn xs" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de chat') }}</button>
		</div>
	</div>
</div>
