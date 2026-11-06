<div class="dbody workspace">
	@if ($committed)
		<div class="alert green dfail"><x-icon name="check" small/><span>{{ $committed }}</span></div>
	@endif

	<div @class(['panel-grid', 'changes-grid' => $changeCount > 0])>
		<x-panel :title="__('Wijzigingen')">
			<x-slot:actions>
				<div class="seg counted" role="group" aria-label="{{ __('Welke wijzigingen') }}">
					@foreach (\Src\Worktree\Enums\DiffViewEnum::cases() as $view)
						@continue($view === \Src\Worktree\Enums\DiffViewEnum::ROUND)
						<button type="button" aria-pressed="{{ $copyView === $view ? 'true' : 'false' }}" wire:click="setDiffView('{{ $view->value }}')" @disabled($viewCounts[$view->value] === 0 && $copyView !== $view)>{{ $view->label() }}<span class="n">{{ $viewCounts[$view->value] }}</span></button>
					@endforeach
				</div>
			</x-slot:actions>
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
				<p class="hint" style="margin:0">{{ $copyView === \Src\Worktree\Enums\DiffViewEnum::UNCOMMITTED ? __('Alles wat hier stond is vastgelegd in een commit.') : ($copyView === \Src\Worktree\Enums\DiffViewEnum::UNPUSHED ? __('Alles staat op origin.') : __('Deze branch veranderde nog niets tegenover :main.', ['main' => $worktree->targetBranch])) }}</p>
			@endif
		</x-panel>

		@if ($changeCount > 0)
			<x-panel :title="__('Commit')">
				<x-slot:actions>
					<x-busy-button action="suggestCommitMessage" class="btn text xs" icon="pencil" :busy="__('Bezig...')">{{ __('Laat de agent schrijven') }}</x-busy-button>
				</x-slot:actions>
				<div class="field" x-data="{ length: @js(mb_strlen($commitMessage)) }">
					<span class="counter-row"><label for="commit-title" style="font-size:13px;font-weight:500;color:var(--fg)">{{ __('Titel') }}</label><span x-text="length + ' / 72'" :style="length > 72 ? 'color:var(--red)' : ''"></span></span>
					<input id="commit-title" @class(['input', 'invalid' => $errors->has('commitMessage')]) type="text" wire:model="commitMessage" x-on:input="length = $event.target.value.length" placeholder="{{ $card->suggestedCommitMessage() }}" autocomplete="off">
					@error('commitMessage')<span class="err">{{ $message }}</span>@enderror
				</div>
				<div class="field">
					<span class="counter-row"><label for="commit-body" style="font-size:13px;font-weight:500;color:var(--fg)">{{ __('Bericht') }}</label><span>{{ __('mag leeg') }}</span></span>
					<textarea id="commit-body" class="textarea" rows="4" wire:model="commitBody"></textarea>
				</div>
			</x-panel>
		@endif
	</div>
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
