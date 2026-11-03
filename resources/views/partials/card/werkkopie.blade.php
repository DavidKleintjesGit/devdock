@if (! $worktree && $mainCheckout)
	<div class="dbody">
		@include('partials.card.main-checkout', ['canCommitHere' => false])
		<section class="dsec">
			<span class="hint">{{ __('Of laat hem staan waar hij staat en koppel de projectmap aan DevDock; dan werkt de agent naast jouw eigen werk.') }}</span>
			<span class="v"><x-busy-button action="adoptExternal" class="btn sm" :busy="__('Koppelen...')">{{ __('Aan DevDock koppelen') }}</x-busy-button></span>
		</section>
	</div>
@elseif (! $worktree && $externalPlace)
	<div class="dbody">
		<section class="dsec">
			<div class="external-place">
				<b><x-icon name="branch" small/>{{ __(':branch staat al uitgecheckt', ['branch' => $card->branch]) }}</b>
				<span>{{ __('In') }} <span class="mono">{{ $externalPlace['path'] }}</span>{{ $externalPlace['isProject'] ? ', ' . __('de projectmap zelf') : '' }}.</span>
				<span class="hint">{{ __('Hier is buiten DevDock aan gewerkt. Koppel je hem, dan toont DevDock de wijzigingen tegenover de hoofdlijn en kunnen agent en lenzen erin werken. Bij opruimen blijft de map staan; alleen de koppeling gaat weg.') }}</span>
				<div class="external-actions">
					<x-busy-button action="adoptExternal" class="btn sm primary" :busy="__('Koppelen...')">{{ __('Aan DevDock koppelen') }}</x-busy-button>
					@if ($externalPlace['isProject'])
						<x-busy-button action="moveToOwnWorkingCopy" class="btn sm" :busy="__('Verhuizen...')">{{ __('Eigen werkkopie maken') }}</x-busy-button>
					@endif
				</div>
			</div>
		</section>
	</div>
@elseif (! $worktree)
	<div class="dbody">
		<section class="dsec">
			<div class="empty">
				<b>{{ __('Nog geen werkkopie') }}</b>
				<span>{{ __('Een eigen map voor deze taak, naast het project, zodat er meer taken tegelijk kunnen lopen.') }}</span>
			</div>
		</section>
		@include('partials.card.new-worktree')
	</div>

	<div class="dfoot">
		<span class="hint">{{ $committed ?? __('De branch wordt van origin afgesneden zonder hem te volgen.') }}</span>
		<x-busy-button action="createWorktree" class="btn primary" :busy="__('Aanmaken...')" :disabled="$card->project === null">{{ __('Werkkopie aanmaken') }}</x-busy-button>
	</div>
@else
	<div class="dbody">
		@if ($committed)
			<div class="alert green dfail"><x-icon name="check" small/><span>{{ $committed }}</span></div>
		@endif

		@if (! $worktree->origin->isOurs())
			<div class="external-note">
				<div class="external-where">
					<span class="label blue">{{ __('extern') }} · {{ $worktree->origin->label() }}</span>
					<span class="mono">{{ $worktree->path }}</span>
				</div>
			</div>
		@endif

		@if ($mainCheckout)
			@include('partials.card.main-checkout', ['canCommitHere' => true])
		@endif

		<section class="dsec">
			<h3 class="eyebrow">{{ __('Stand van de branch') }}</h3>
			<div class="list">
				@if ($card->conflictedFiles !== [])
					<div class="kv">
						<div class="v">
							<span class="sdot conflict"></span>
							<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice('Conflict met :main in :count bestand|Conflict met :main in :count bestanden', count($card->conflictedFiles), ['main' => $worktree->mainBranch, 'count' => count($card->conflictedFiles)]) }}</b><span class="hint">{{ $card->pullRequests->isNotEmpty() ? __('Bitbucket kan de pull request zo niet mergen.') : __('Samenvoegen met :main botst nu.', ['main' => $worktree->mainBranch]) }}</span></span>
							<x-busy-button action="resolveConflictsWithAgent" class="btn sm" :busy="__('Versturen...')">{{ __('Laat de agent oplossen') }}</x-busy-button>
						</div>
						<ul style="margin:0 0 0 21px;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px">
							@foreach ($card->conflictedFiles as $conflictedFile)
								<li class="mono" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $conflictedFile }}</li>
							@endforeach
						</ul>
					</div>
				@endif

				@if ($worktree->behind > 0)
					<div class="kv">
						<div class="v">
							<span class="sdot busy" style="animation:none"></span>
							<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice(':count commit achter op :main|:count commits achter op :main', $worktree->behind, ['count' => $worktree->behind, 'main' => $worktree->mainBranch]) }}</b><span class="hint">{{ __('Tests kunnen hier slagen en op de pull request alsnog vallen.') }}</span></span>
							<x-busy-button action="rebaseOnMain" class="btn sm" :busy="__('Rebasen...')">{{ __('Rebase op :main', ['main' => $worktree->mainBranch]) }}</x-busy-button>
						</div>
					</div>
				@endif

				@if ($worktree->ahead > 0)
					<div class="kv">
						<div class="v">
							<span class="sdot yours"></span>
							<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice(':count commit nog niet gepusht|:count commits nog niet gepusht', $worktree->ahead, ['count' => $worktree->ahead]) }}</b><span class="hint">@if ($worktree->onlyOnUpstream > 0){{ trans_choice(':upstream heeft nog :count commit die hier niet staat, zoals de versie van vóór een rebase. Pushen overschrijft die.|:upstream heeft nog :count commits die hier niet staan, zoals de versie van vóór een rebase. Pushen overschrijft die.', $worktree->onlyOnUpstream, ['count' => $worktree->onlyOnUpstream, 'upstream' => $worktree->upstream]) }}@elseif ($worktree->upstream){{ __('Al gecommit, nog niet op :upstream.', ['upstream' => $worktree->upstream]) }}@else{{ __('Al gecommit; de eerste push maakt origin/:branch aan.', ['branch' => $worktree->branch]) }}@endif</span></span>
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

				@if ($worktree->behind === 0 && $worktree->ahead === 0 && $outdatedRules === [] && $card->conflictedFiles === [])
					<div class="kv"><div class="v"><span class="sdot quiet"></span><span class="grow" style="font-family:inherit">{{ __('Bij met :main, alles gepusht.', ['main' => $worktree->mainBranch]) }}</span></div></div>
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
		</section>

		<section class="dsec">
			<div class="headrow changes-head">
				<h3 class="eyebrow grow">{{ __('Wijzigingen') }}</h3>
				<div class="seg counted" role="group" aria-label="{{ __('Welke wijzigingen') }}">
					@foreach (\Src\Worktree\Enums\DiffViewEnum::cases() as $view)
						<button type="button" aria-pressed="{{ $copyView === $view ? 'true' : 'false' }}" wire:click="setDiffView('{{ $view->value }}')" @disabled($viewCounts[$view->value] === 0 && $copyView !== $view)>{{ $view->label() }}<span class="n">{{ $viewCounts[$view->value] }}</span></button>
					@endforeach
				</div>
			</div>
			<p class="hint" style="margin:0">
				@if ($copyView === \Src\Worktree\Enums\DiffViewEnum::UNCOMMITTED)
					{{ __('Veranderd sinds de laatste commit. Wat aangevinkt is, gaat mee in de volgende commit.') }}@if ($changeCount > 0) · <span class="plus">+{{ $added }}</span> <span class="minus">−{{ $removed }}</span> · {{ __(':picked van :total gaan mee', ['picked' => $pickedCount, 'total' => $changeCount]) }}@endif
				@elseif ($copyView === \Src\Worktree\Enums\DiffViewEnum::UNPUSHED)
					@if ($newCommitCount === 0){{ __('Alleen wat nog niet gecommit is') }}@else{{ trans_choice(':count commit met wijzigingen die nog niet op origin staan|:count commits met wijzigingen die nog niet op origin staan', $newCommitCount, ['count' => $newCommitCount]) }}{{ $changeCount > 0 ? ', ' . __('plus wat nog niet gecommit is') : '' }}@endif · <span class="plus">+{{ $viewAdded }}</span> <span class="minus">−{{ $viewRemoved }}</span>
				@else
					{{ __('Alles wat deze branch veranderde, gecommit en niet gecommit samen. Wat de pull request laat zien.') }} · <span class="plus">+{{ $viewAdded }}</span> <span class="minus">−{{ $viewRemoved }}</span>
				@endif
			</p>
			@if ($viewChanges->isNotEmpty())
				<div class="list">
					@foreach ($viewChanges as $row)
						@if ($copyView === \Src\Worktree\Enums\DiffViewEnum::UNCOMMITTED)
							<div @class(['filerow', 'off' => ! $row['isPicked']]) wire:key="change-{{ $row['change']->path }}">
								<label class="tick cell">
									<input type="checkbox" @checked($row['isPicked']) wire:click="toggleCommitFile('{{ $row['change']->path }}')" aria-label="{{ __(':name meenemen', ['name' => $row['change']->name()]) }}">
									<span class="mark"></span>
								</label>
								<button class="filerow grow" type="button" style="padding:0;min-height:44px" wire:click="openDiff('{{ $row['change']->path }}', '{{ $copyView->value }}')">
									<span class="name"><b>{{ $row['change']->name() }}</b><small>{{ $row['change']->folder() }}{{ $row['isPicked'] ? '' : ' · ' . __('gaat niet mee') }}</small></span>
									<span class="delta">@if ($row['change']->added)<span class="plus">+{{ $row['change']->added }}</span>@endif @if ($row['change']->removed)<span class="minus">−{{ $row['change']->removed }}</span>@endif</span>
									<x-icon name="chev" small/>
								</button>
							</div>
						@else
							<button class="filerow" type="button" wire:click="openDiff('{{ $row['change']->path }}', '{{ $copyView->value }}')" wire:key="view-change-{{ $copyView->value }}-{{ $row['change']->path }}">
								<span class="name"><b>{{ $row['change']->name() }}@if ($row['change']->isNew())<span class="label green" style="margin-left:6px">{{ __('nieuw') }}</span>@elseif ($row['change']->isGone())<span class="label red" style="margin-left:6px">{{ __('weg') }}</span>@endif</b><small>{{ $row['change']->folder() }}</small></span>
								<span class="delta">@if ($row['change']->added)<span class="plus">+{{ $row['change']->added }}</span>@endif @if ($row['change']->removed)<span class="minus">−{{ $row['change']->removed }}</span>@endif</span>
								<x-icon name="chev" small/>
							</button>
						@endif
					@endforeach
					@if ($hiddenViewChanges > 0)
						<button class="row" type="button" style="color:var(--accent);font-size:13px;min-height:42px" wire:click="$set('showAllChanges', true)">{{ __('Nog :count bestanden', ['count' => $hiddenViewChanges]) }}</button>
					@endif
				</div>
			@else
				<p class="hint" style="margin:0">{{ $copyView === \Src\Worktree\Enums\DiffViewEnum::UNCOMMITTED ? __('Alles wat hier stond is vastgelegd in een commit.') : ($copyView === \Src\Worktree\Enums\DiffViewEnum::UNPUSHED ? __('Alles staat op origin.') : __('Deze branch veranderde nog niets tegenover :main.', ['main' => $worktree->mainBranch])) }}</p>
			@endif
		</section>

		@if ($changeCount > 0)
			<section class="dsec">
				<div class="headrow">
					<h3 class="eyebrow">{{ __('Commit') }}</h3>
					<x-busy-button action="suggestCommitMessage" class="btn text xs" icon="pencil" :busy="__('Bezig...')">{{ __('Laat de agent schrijven') }}</x-busy-button>
				</div>
				<div class="field" x-data="{ length: @js(mb_strlen($commitMessage)) }">
					<span class="counter-row"><label for="commit-title" style="font-size:13px;font-weight:500;color:var(--fg)">{{ __('Titel') }}</label><span x-text="length + ' / 72'" :style="length > 72 ? 'color:var(--red)' : ''"></span></span>
					<input id="commit-title" @class(['input', 'invalid' => $errors->has('commitMessage')]) type="text" wire:model="commitMessage" x-on:input="length = $event.target.value.length" placeholder="{{ $card->suggestedCommitMessage() }}" autocomplete="off">
					@error('commitMessage')<span class="err">{{ $message }}</span>@enderror
				</div>
				<div class="field">
					<span class="counter-row"><label for="commit-body" style="font-size:13px;font-weight:500;color:var(--fg)">{{ __('Bericht') }}</label><span>{{ __('mag leeg') }}</span></span>
					<textarea id="commit-body" class="textarea" rows="4" wire:model="commitBody"></textarea>
				</div>
			</section>
		@endif

		<section class="dsec">
			<details class="fold" wire:ignore.self @if ($databaseDialog || $upstreamDialog) open @endif>
				<summary>
					<x-icon name="chev" small/>
					<b class="grow">{{ __('Deze kopie') }}</b>
					@if ($database)<span class="hint">{{ $database->isShared ? __('database gedeeld') : __('eigen database') }}</span>@endif
					<span class="hint">{{ $worktree->upstream ? __('volgt :upstream', ['upstream' => $worktree->upstream]) : __('volgt nog niets') }}</span>
				</summary>
				<div class="foldbody">
					<div class="kv">
						<span class="k">{{ __('Map') }}</span>
						<span class="v"><span class="grow">{{ $worktree->path }}</span><button class="btn xs" type="button" wire:click="openInClaude">{{ __('Openen') }}</button></span>
						<span class="hint">{{ __('Gemaakt op :date.', ['date' => $worktree->createdAt?->translatedFormat('j F')]) }}@if ($worktree->site) · <a href="{{ $worktree->url() }}" target="_blank" rel="noopener">{{ $worktree->site }}</a>@endif</span>
					</div>

					@if ($database)
						<div class="kv">
							<span class="k">{{ __('Database') }}</span>
							<span class="v"><span class="grow">{{ $database->isSqlite() ? basename((string) $database->database) : $database->database }}</span>@if ($database->isShared)<span class="label amber">{{ __('gedeeld met het project') }}</span>@else<span class="label green">{{ __('eigen') }}</span>@endif</span>
							<span class="hint">{{ $database->serverLabel() }} · {{ __('uit de .env van deze kopie') }}</span>
							@if ($database->isShared)
								<span class="hint" style="color:var(--amber)">{{ __('Een migratie hier verandert ook de database van het project en van andere werkkopieën.') }}</span>
							@endif
							<span class="v" style="margin-top:4px">
								<button class="btn xs" type="button" wire:click="startDatabase">{{ __('Eigen database aanmaken') }}</button>
								<button class="btn xs" type="button" wire:click="startChooseDatabase">{{ __('Een andere kiezen') }}</button>
							</span>
						</div>
					@endif

					@if ($testDatabase)
						<div class="kv">
							<span class="k">{{ __('Testdatabase') }}</span>
							<span class="v"><span class="grow">{{ $testDatabase->database }}</span>@if ($testDatabase->isShared)<span class="label amber">{{ __('gedeeld met het project') }}</span>@else<span class="label green">{{ __('eigen') }}</span>@endif</span>
							<span class="hint">{{ $testDatabase->serverLabel() }} · {{ __('uit de .env.testing van deze kopie') }}</span>
							@if ($testDatabase->isShared)
								<span class="hint" style="color:var(--amber)">{{ __('Draaien hier en ergens anders tegelijk tests, dan legen ze elkaars database.') }}</span>
								<span class="v" style="margin-top:4px"><x-busy-button action="giveOwnTestDatabase" class="btn xs" :busy="__('Aanmaken...')">{{ __('Eigen testdatabase aanmaken') }}</x-busy-button></span>
							@endif
						</div>
					@endif

					<div class="kv">
						<span class="k">{{ __('Upstream, hier gaat een push heen') }}</span>
						<span class="v"><span class="grow">{{ $worktree->upstream ?? __('nog niets; de eerste push maakt origin/:branch aan', ['branch' => $worktree->branch]) }}</span><button class="btn xs" type="button" wire:click="startUpstream">{{ __('Wijzigen') }}</button></span>
						<span class="hint">{{ __(':ahead voor, :behind achter op :main', ['ahead' => $worktree->ahead, 'behind' => $worktree->behind, 'main' => $worktree->mainBranch]) }}</span>
					</div>

					<div class="kv">
						<span class="k">{{ __('Gemaakt vanaf') }}</span>
						<span class="v"><span class="grow">origin/{{ $worktree->mainBranch }}</span></span>
						@if ($worktree->behind > 0)<span class="hint">{{ __('Inmiddels :count commits achter. Rebasen staat bovenaan.', ['count' => $worktree->behind]) }}</span>@endif
					</div>

					@if ($packages !== [])
						<div class="kv">
							<span class="k">{{ __('Pakketten') }}</span>
							@foreach ($packages as $package)
								<span class="v"><span class="grow">{{ $package['folder'] }}</span><span class="hint">{{ $package['isBorrowed'] ? __('komt uit het project') : ($package['isSameLock'] ? __('nog niet geleend') : __('eigen installatie nodig')) }}</span></span>
							@endforeach
							<span class="hint">{{ __('Niet opnieuw geïnstalleerd zolang het slotbestand gelijk is aan dat van het project. Wijkt het af, dan installeert DevDock ze hier apart.') }}</span>
						</div>
					@endif

					<div class="kv">
						<span class="k">{{ __('Overgenomen uit het project') }} · {{ trans_choice(':count bestand dat git niet meeneemt|:count bestanden die git niet meeneemt', count($carried), ['count' => count($carried)]) }}</span>
						<span class="chips">@foreach ($carried as $entry)<span class="path">{{ $entry }}</span>@endforeach</span>
						<span class="v"><x-busy-button action="carryOverAgain" class="btn xs" :busy="__('Overnemen...')">{{ __('Opnieuw overnemen') }}</x-busy-button></span>
					</div>

					<div class="kv">
						<span class="k">{{ __('Mag ook inzien') }}</span>
						<span class="v"><span class="grow">{{ $readableProjects === [] ? __('alleen zijn eigen werkkopie') : implode(', ', $readableProjects) }}</span><button class="btn xs" type="button" wire:click="setDrawerTab('Koppelingen')">{{ __('Wijzigen') }}</button></span>
						<span class="hint">{{ __('De agent mag daar lezen, niet schrijven.') }}</span>
					</div>

					<div class="kv">
						<span class="k">{{ __('Opruimen') }}</span>
						<span class="hint">{{ $card->isDone() ? __('De pull request is gemerged. Dit gaat weg:') : __('Kan pas als de pull request gemerged is. Dan gaat dit weg:') }}</span>
						<ul class="cleanup">
							@foreach ($cleanUp as $line)<li>{{ $line }}</li>@endforeach
						</ul>
						<span class="v"><x-busy-button action="removeWorktree" class="btn xs" :busy="__('Opruimen...')" :disabled="! $card->isDone()">{{ __('Opruimen') }}</x-busy-button></span>
					</div>
				</div>
			</details>
		</section>
	</div>

	<div class="dfoot">
		@if ($changeCount > 0)
			<span class="grow" style="display:flex;align-items:center;gap:10px;font-size:13px;color:var(--fg-2)">
				<button class="switch" type="button" role="switch" aria-checked="{{ $pushAfterCommit ? 'true' : 'false' }}" wire:click="$toggle('pushAfterCommit')" aria-label="{{ __('Daarna pushen') }}"></button>{{ __('Daarna pushen') }}
			</span>
			<x-busy-button action="commitChanges" class="btn primary" :busy="__('Committen...')" :disabled="$pickedCount === 0">{{ trans_choice('Committen · :count bestand|Committen · :count bestanden', $pickedCount, ['count' => $pickedCount]) }}</x-busy-button>
		@elseif ($worktree->ahead > 0)
			<span class="hint">{{ __('Niets meer te committen.') }}</span>
			<x-busy-button action="pushBranch" class="btn primary" :busy="__('Pushen...')">{{ $worktree->onlyOnUpstream > 0 ? trans_choice('Overschrijven op origin · :count commit|Overschrijven op origin · :count commits', $worktree->ahead, ['count' => $worktree->ahead]) : trans_choice('Pushen · :count commit|Pushen · :count commits', $worktree->ahead, ['count' => $worktree->ahead]) }}</x-busy-button>
		@else
			<span class="hint">{{ __('Schoon: niets te committen of te pushen.') }}</span>
		@endif
	</div>

	@if ($databaseDialog === 'new' && $database)
		@include('partials.card.database-new')
	@elseif ($databaseDialog === 'choose' && $database)
		@include('partials.card.database-choose')
	@endif

	@if ($upstreamDialog)
		@include('partials.card.upstream')
	@endif
@endif
