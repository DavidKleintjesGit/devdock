<div class="dbody workspace">
	<x-panel :title="__('Stand van de branch')" :padded="false">
		<div class="list">
			@if ($branchChain)
				<div class="kv">
					<div class="v">
						<span class="sdot {{ $blockingLink ? 'conflict' : 'quiet' }}"></span>
						<span class="grow branch-chain">
							<span class="mono muted">{{ $branchChain->mainBranch }}</span>
							@foreach ($branchChain->linksFromMain() as $link)
								<span class="chain-arrow" aria-hidden="true">→</span>
								@if ($link->branch === $worktree->branch)
									<b class="mono">{{ $link->branch }}</b>
								@elseif ($link->cardKey)
									<button class="mono chain-link" type="button" wire:click="open('{{ $link->cardKey }}')" title="{{ __('Open de kaart van :branch', ['branch' => $link->branch]) }}">{{ $link->branch }}</button>
								@else
									<span class="mono">{{ $link->branch }}</span>
								@endif
								@if ($link->behind > 0 && $link->branch !== $worktree->branch)
									<span class="kbehind" title="{{ trans_choice(':count commit achter op :main|:count commits achter op :main', $link->behind, ['count' => $link->behind, 'main' => $link->target]) }}">↓{{ $link->behind }}</span>
								@endif
							@endforeach
						</span>
					</div>
					@if ($levelFirst)
						<p class="alert" style="margin:0 0 0 21px">{{ $levelFirst }}</p>
					@endif
				</div>
			@endif

			@if ($worktree->isRebasing)
				<div class="kv">
					<div class="v">
						<span class="sdot conflict"></span>
						<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ __('Rebase op :main staat open', ['main' => $worktree->targetBranch]) }}</b><span class="hint">{{ $openConflicts !== [] ? trans_choice('Nog :count conflict op te lossen.|Nog :count conflicten op te lossen.', count($openConflicts), ['count' => count($openConflicts)]) : __('Alle conflicten zijn opgelost; de rebase moet nog verder.') }}</span></span>
						<x-busy-button action="resolveConflictsWithAgent" class="btn sm primary" :busy="__('Versturen...')">{{ __('Agent laten afmaken') }}</x-busy-button>
						<x-busy-button action="abortRebase" class="btn sm" :busy="__('Afbreken...')">{{ __('Rebase afbreken') }}</x-busy-button>
					</div>
					<ul style="margin:0 0 0 21px;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px">
						@foreach ($openConflicts as $conflictedFile)
							<li class="mono" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $conflictedFile }}</li>
						@endforeach
					</ul>
				</div>
			@elseif ($card->conflictedFiles !== [])
				<div class="kv">
					<div class="v">
						<span class="sdot conflict"></span>
						<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice('Conflict met :main in :count bestand|Conflict met :main in :count bestanden', count($card->conflictedFiles), ['main' => $worktree->targetBranch, 'count' => count($card->conflictedFiles)]) }}</b><span class="hint">{{ trans_choice(':count commit achter.|:count commits achter.', $worktree->behind, ['count' => $worktree->behind]) }} {{ $card->pullRequests->isNotEmpty() ? __('Bitbucket kan de pull request zo niet mergen.') : __('Samenvoegen met :main botst nu.', ['main' => $worktree->targetBranch]) }}</span></span>
						<x-busy-button action="resolveConflictsWithAgent" class="btn sm primary" :busy="__('Rebase beginnen...')" :disabled="$worktree->changedFiles > 0 || $blockingLink !== null">{{ __('Conflicten oplossen') }}</x-busy-button>
					</div>
					<ul style="margin:0 0 0 21px;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px">
						@foreach ($card->conflictedFiles as $conflictedFile)
							<li class="mono" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $conflictedFile }}</li>
						@endforeach
					</ul>
					@if ($worktree->changedFiles > 0)
						<p class="alert" style="margin:0 0 0 21px">{{ trans_choice(':count bestand is nog niet gecommit. Commit eerst, dan kan dit.|:count bestanden zijn nog niet gecommit. Commit eerst, dan kan dit.', $worktree->changedFiles, ['count' => $worktree->changedFiles]) }}</p>
					@else
						<p class="hint" style="margin:0 0 0 21px">{{ __('De agent mag voor één beurt rebasen op :main en deze bestanden oplossen. Daarna vervalt dat weer. Pushen blijft jouw knop.', ['main' => $worktree->targetBranch]) }}</p>
					@endif
				</div>
			@elseif ($worktree->behind > 0)
				<div class="kv">
					<div class="v">
						<span class="sdot busy" style="animation:none"></span>
						<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice(':count commit achter op :main|:count commits achter op :main', $worktree->behind, ['count' => $worktree->behind, 'main' => $worktree->targetBranch]) }}</b><span class="hint">{{ __('Geen conflicten. Tests kunnen hier slagen en op de pull request alsnog vallen.') }}</span></span>
						<x-busy-button action="rebaseOnMain" class="btn sm" :busy="__('Rebasen...')" :disabled="$worktree->changedFiles > 0 || $blockingLink !== null">{{ __('Rebase') }}</x-busy-button>
					</div>
					@if ($worktree->changedFiles > 0)
						<p class="alert" style="margin:0 0 0 21px">{{ trans_choice(':count bestand is nog niet gecommit. Commit eerst, dan kan de rebase.|:count bestanden zijn nog niet gecommit. Commit eerst, dan kan de rebase.', $worktree->changedFiles, ['count' => $worktree->changedFiles]) }}</p>
					@endif
				</div>
			@endif

			@if ($worktree->ahead > 0)
				<div class="kv">
					<div class="v">
						<span class="sdot yours"></span>
						<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice(':count commit nog niet gepusht|:count commits nog niet gepusht', $worktree->ahead, ['count' => $worktree->ahead]) }}</b><span class="hint">@if ($lostByOverwrite !== []){{ trans_choice(':upstream heeft :count commit die hier niet staat en niet uit een rebase komt: :subjects. Overschrijven gooit die weg.|:upstream heeft :count commits die hier niet staan en niet uit een rebase komen: :subjects. Overschrijven gooit die weg.', count($lostByOverwrite), ['count' => count($lostByOverwrite), 'upstream' => $worktree->upstream, 'subjects' => implode(', ', $lostByOverwrite)]) }}@elseif ($worktree->onlyOnUpstream > 0){{ trans_choice('Gerebased. Op :upstream staat nog de versie van vóór de rebase; deze :count commit komt ervoor in de plaats en er gaat geen werk verloren.|Gerebased. Op :upstream staat nog de versie van vóór de rebase; deze :count commits komen ervoor in de plaats en er gaat geen werk verloren.', $worktree->ahead, ['count' => $worktree->ahead, 'upstream' => $worktree->upstream]) }}@elseif ($worktree->upstream){{ __('Al gecommit, nog niet op :upstream.', ['upstream' => $worktree->upstream]) }}@else{{ __('Al gecommit; de eerste push maakt origin/:branch aan.', ['branch' => $worktree->branch]) }}@endif</span></span>
						<button class="btn sm" type="button" wire:click="openUnpushedDiff">{{ __('Bekijken') }}</button>
						<x-busy-button action="pushBranch" class="btn sm" :busy="__('Pushen...')">{{ $worktree->onlyOnUpstream > 0 ? __('Overschrijven') : __('Pushen') }}</x-busy-button>
					</div>
					<ol style="margin:0 0 0 21px;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--fg-2)">
						@foreach ($unpushed as $commit)
							<li style="display:flex;gap:10px"><span class="mono muted" style="font-size:12px">{{ $commit['sha'] }}</span><span class="grow">{{ $commit['title'] }}</span><span class="hint" style="font-size:12px">{{ $commit['at']->diffForHumans(short: true) }}</span></li>
						@endforeach
					</ol>
				</div>
			@endif

			@foreach ($outdatedRules as $standing)
				<div class="kv">
					<div class="v">
						<span class="sdot busy" style="box-shadow:none"></span>
						<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ __(':name loopt achter op :library', ['name' => $standing->item->name, 'library' => $standing->item->library]) }}</b><span class="hint">{{ __('Deze werkkopie heeft :version, de bibliotheek :latest.', ['version' => $standing->onVersion?->label(), 'latest' => $standing->item->latest()?->label()]) }}</span></span>
						<a class="btn sm" href="{{ route('rules', ['project' => $card->project, 'file' => $standing->path]) }}">{{ __('Bijwerken') }}</a>
					</div>
				</div>
			@endforeach

			@if ($worktree->behind === 0 && $worktree->ahead === 0 && $outdatedRules === [] && $card->conflictedFiles === [] && ! $worktree->isRebasing)
				<div class="kv"><div class="v"><span class="sdot quiet"></span><span class="grow" style="font-family:inherit">{{ __('Bij met :main, alles gepusht.', ['main' => $worktree->targetBranch]) }}</span></div></div>
			@endif

			@if ($lastRebase)
				<div class="kv" x-data>
					<div class="v">
						<span class="sdot quiet"></span>
						<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ __('Vorige rebase · :when', ['when' => $lastRebase->at->diffForHumans()]) }}</b><span class="hint">{{ __('Stand daarvoor:') }} <span class="mono" style="font-size:12px">{{ substr($lastRebase->shaBefore, 0, 9) }}</span></span></span>
						<button class="btn text xs" type="button" x-on:click="navigator.clipboard.writeText(@js($lastRebase->shaBefore))">{{ __('Kopieer sha') }}</button>
					</div>
				</div>
			@endif
		</div>
	</x-panel>
</div>
