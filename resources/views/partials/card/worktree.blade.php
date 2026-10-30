<x-fold :title="__('Koppelingen')" :summary="($card->project ?? __('geen project')) . ' · ' . ($card->branch ?? __('geen branch'))" :open="$openOnWorktree['links']">
	<div class="field">
		<div class="fieldhead">
			<h3 class="eyebrow">{{ __('Project') }}</h3>
			<span class="muted" style="font-size:12.5px">{{ __('hier werkt de agent') }}</span>
		</div>
		@if ($card->project)
			<div class="linklist">
				<div class="linkrow static">
					<span class="dot green"></span>
					<span class="grow">
						<b class="mono">{{ $card->project }}</b>
						<small>{{ __('actief project') }}</small>
					</span>
					<button class="btn icon ghost" type="button" aria-label="{{ __('Koppeling weghalen') }}" wire:click="unlink('project', '{{ $card->project }}')"><x-icon name="x" small/></button>
				</div>
			</div>
		@else
			<span class="note amber"><x-icon name="alert" small/><span>{{ __('Zonder project kan de agent niet aan het werk.') }}</span></span>
		@endif
		<div class="row">
			<select class="select" wire:model="linkProject">
				<option value="">{{ __('Kies een project') }}</option>
				@foreach ($projectChoices as $name)
					<option value="{{ $name }}">{{ $name }}</option>
				@endforeach
			</select>
			<button class="btn" type="button" wire:click="link('project')">{{ __('Project vastzetten') }}</button>
		</div>
	</div>

	<div class="field">
		<div class="fieldhead">
			<h3 class="eyebrow">{{ __('Mag ook inzien') }}</h3>
			<span class="muted" style="font-size:12.5px">{{ __('andere projecten') }}</span>
		</div>
		@if ($readableProjects)
			<div class="linklist">
				@foreach ($readableProjects as $name)
					<div class="linkrow static">
						<span class="dot green"></span>
						<span class="grow">
							<b class="mono">{{ $name }}</b>
							<small>{{ __('de agent mag hier lezen en schrijven') }}</small>
						</span>
						<button class="btn icon ghost" type="button" aria-label="{{ __('Toegang weghalen') }}" wire:click="unlink('readable_project', '{{ $name }}')"><x-icon name="x" small/></button>
					</div>
				@endforeach
			</div>
		@else
			<span class="muted" style="font-size:12.5px">{{ __('De agent komt nergens anders dan in zijn eigen werkkopie.') }}</span>
		@endif
		<div class="row">
			<select class="select" wire:model="linkReadableProject">
				<option value="">{{ __('Kies een project') }}</option>
				@foreach ($projectChoices as $name)
					<option value="{{ $name }}">{{ $name }}</option>
				@endforeach
			</select>
			<button class="btn" type="button" wire:click="link('readable_project')">{{ __('Toegang geven') }}</button>
		</div>
	</div>

	@if ($card->project)
		<div class="field">
			<div class="fieldhead">
				<h3 class="eyebrow">{{ __('Branch') }}</h3>
				<span class="muted" style="font-size:12.5px">{{ $branchRows ? __('één actief') : __('mag ook later') }}</span>
			</div>
			@if ($branchRows)
				<div class="linklist">
					@foreach ($branchRows as $row)
						<div class="linkrow static">
							<span class="dot {{ $row['isActive'] ? 'green' : '' }}"></span>
							<span class="grow">
								<b class="mono">{{ $row['value'] }}</b>
								<small>{{ $row['isPinned'] ? __('vastgezet') : __('afgeleid uit de naam') }}</small>
							</span>
							@if ($row['isActive'])
								<span class="chip green">{{ __('actief') }}</span>
							@else
								<button class="btn sm" type="button" wire:click="activate('branch', '{{ $row['value'] }}')">{{ __('Op actief zetten') }}</button>
							@endif
							@if ($row['isPinned'])
								<button class="btn icon ghost" type="button" aria-label="{{ __('Koppeling weghalen') }}" wire:click="unlink('branch', '{{ $row['value'] }}')"><x-icon name="x" small/></button>
							@endif
						</div>
					@endforeach
				</div>
			@endif
			@if ($branchChoices)
				<div class="row">
					<select class="select" wire:model="linkBranch">
						<option value="">{{ __('Kies een bestaande branch') }}</option>
						@foreach ($branchChoices as $branch)
							<option value="{{ $branch }}">{{ $branch }}</option>
						@endforeach
					</select>
					<button class="btn" type="button" wire:click="link('branch')">{{ __('Branch vastzetten') }}</button>
				</div>
			@endif
			<div class="row">
				<input class="input" type="text" wire:model="newBranch" value="{{ $newBranch }}" placeholder="{{ __('Een nieuwe branch, bijvoorbeeld feature/CSM-4120') }}">
				<x-busy-button action="suggestBranchName" class="btn" icon="refresh" :busy="__('Bezig...')">{{ __('Laat de agent het bedenken') }}</x-busy-button>
			</div>
			<span class="muted" style="font-size:12.5px">{{ __('Dit is een voorstel; je mag het aanpassen. Een naam die nog niet bestaat wordt van de hoofdbranch afgesneden.') }}</span>
		</div>
	@endif
</x-fold>

<x-fold :title="__('Werkkopie')" :summary="$worktree?->path ?? __('nog geen werkkopie')" :open="$openOnWorktree['worktree']">
	@if ($worktree)
		<dl class="facts">
			<div><dt>{{ __('Staat') }}</dt><dd>{{ $worktree->waitingLabel() ?? __('Schoon') }}</dd></div>
			<div><dt>{{ __('Pad') }}</dt><dd class="mono">{{ $worktree->path }}</dd></div>
			@if ($worktree->site)
				<div><dt>{{ __('Adres') }}</dt><dd><a class="mono" href="{{ $worktree->url() }}" target="_blank" rel="noopener">{{ $worktree->site }}</a></dd></div>
			@endif
		</dl>
		@if ($worktree->dependenciesMissing)
			<span class="note amber"><x-icon name="alert" small/><span>{{ __('Nog geen vendor: het slotbestand wijkt af van het project, dus hier moet composer install draaien.') }}</span></span>
		@endif
		<span class="muted" style="font-size:12.5px">{{ $step === \Src\Review\Enums\WorkflowStepEnum::MERGED ? __('De pull request is gemerged; de werkkopie mag weg.') : __('De agent draait in deze map. Opruimen kan pas als de pull request gemerged is.') }}</span>
	@else
		<div class="empty">
			<span class="bubble"><x-icon name="folder"/></span>
			<b>{{ __('Nog geen werkkopie') }}</b>
			<span>{{ __('De agent maakt er zelf een zodra hij aan het werk gaat, of je maakt hem hieronder alvast aan.') }}</span>
		</div>
	@endif
</x-fold>

@if ($failure)
	<span class="note red"><x-icon name="alert" small/><span>{{ $failure }}</span></span>
@endif

@if ($readyToCommit)
	<span class="note green"><x-icon name="check" small/><span>{{ __('De lenzen gaven groen licht. Loop de open wijzigingen na, commit ze en push daarna zelf.') }}</span></span>
@endif

<x-fold :title="__('Nog niet gecommit')" :summary="$uncommittedSummary" :open="$openOnWorktree['uncommitted']">
	@if ($changes->isNotEmpty())
		<div class="linklist">
			@foreach ($changes as $change)
				<button class="linkrow" type="button" wire:click="openDiff('{{ $change->path }}')">
					<span class="dot {{ $change->kind->tone() }}"></span>
					<span class="grow">
						<b class="mono" style="font-weight:500">{{ $change->name() }}</b>
						<small class="mono">{{ $change->folder() }}</small>
					</span>
					<span class="muted mono" style="font-size:12px">{{ $change->sizeLabel() }}</span>
				</button>
			@endforeach
		</div>
		<span class="muted" style="font-size:12.5px">{{ __('Klik een bestand om de wijziging te lezen.') }}</span>
	@else
		<span class="muted" style="font-size:12.5px">{{ __('Alles wat hier stond is vastgelegd in een commit.') }}</span>
	@endif
</x-fold>

<x-fold :title="__('Op de branch')" :summary="$branchSummary" :open="$openOnWorktree['branch']">
	@if ($branchFiles)
		<div class="linklist">
			@foreach ($branchFiles as $file)
				<button class="linkrow" type="button" wire:click="openDiff('{{ $file }}')">
					<span class="dot {{ $openDiffFile === $file ? 'green' : '' }}"></span>
					<span class="grow">
						<b class="mono" style="font-weight:500">{{ basename($file) }}</b>
						<small class="mono">{{ dirname($file) }}</small>
					</span>
					<x-icon name="chev" small/>
				</button>
			@endforeach
		</div>
		<span class="muted" style="font-size:12.5px">{{ __('Dit is wat de lenzen beoordeelden: alles wat deze branch anders doet dan de hoofdlijn, gecommit of niet.') }}</span>
	@else
		<span class="muted" style="font-size:12.5px">{{ __('Nog niets veranderd op deze branch.') }}</span>
	@endif
</x-fold>

@if ($changes->isNotEmpty())
	<x-fold :title="__('Commitbericht')" :open="$openOnWorktree['message']">
		<input id="commit-message" class="input" type="text" wire:model="commitMessage" value="{{ $commitMessage }}" placeholder="{{ $card->ticketKey ? $card->ticketKey . ' - ' : '' }}{{ __('wat je hebt gedaan') }}" autocomplete="off">
		@error('commitMessage')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
		<textarea class="textarea grow" style="min-height:96px" wire:model="commitBody" placeholder="{{ __('Toelichting, regel per wijziging') }}">{{ $commitBody }}</textarea>
		<div class="row">
			<button class="btn sm" type="button" wire:click="useReportedCommitMessage"><x-icon name="copy" small/>{{ __('Uit het laatste antwoord') }}</button>
			<x-busy-button action="suggestCommitMessage" class="btn sm" icon="refresh" :busy="__('Bezig...')">{{ __('Laat de agent het schrijven') }}</x-busy-button>
		</div>
	</x-fold>
@endif

<div class="dfoot">
	@if ($worktree)
		<x-busy-button action="commitChanges" class="btn primary" icon="check" :busy="__('Committen...')" :disabled="$changes->isEmpty()">{{ __('Committen') }}</x-busy-button>
		<x-busy-button action="pushBranch" class="btn {{ $confirmingPush ? 'danger solid' : '' }}" :busy="__('Pushen...')" :disabled="$worktree->ahead === 0">{{ $pushLabel }}</x-busy-button>
		<x-busy-button action="removeWorktree" class="btn danger" :busy="__('Opruimen...')" :disabled="$step !== \Src\Review\Enums\WorkflowStepEnum::MERGED">{{ __('Opruimen') }}</x-busy-button>
	@else
		<x-busy-button action="createWorktree" class="btn primary" :busy="__('Aanmaken...')" :disabled="$card->project === null">{{ __('Werkkopie aanmaken') }}</x-busy-button>
	@endif
	<span class="hint">{{ $committed ?? $card->branch ?? __('Nog geen branch') }}</span>
</div>
