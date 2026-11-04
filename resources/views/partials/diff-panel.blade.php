<div class="scrim" style="z-index:65" wire:click="closeDiff"></div>
<aside class="drawer wide over diff-drawer" role="dialog" aria-labelledby="diff-title" x-data="viewedFiles(@js($card->id), @js($diffFingerprints))" x-init="$nextTick(() => document.getElementById(@js($openAnchor))?.scrollIntoView({ block: 'start' }))">
	<div class="resize-edge" data-resize="--diff-width" data-edge="start" data-min="560" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
	<div class="dbar">
		<span class="kind" id="diff-title">{{ __('Wijzigingen') }} · {{ $card->ticketKey ?? $card->title }}</span>
		<button class="icon-btn" type="button" wire:click="closeDiff" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="diff-toolbar">
		<b class="grow">{{ __('Alle wijzigingen') }}</b>
		<span class="hint" x-text="@js(__(':viewed van :total bestanden bekeken', ['total' => count($diffFiles)])).replace(':viewed', viewedCount())"></span>
		<div class="diff-views">
			<div class="seg counted" role="group" aria-label="{{ __('Welke wijzigingen') }}">
				@foreach (\Src\Worktree\Enums\DiffViewEnum::cases() as $view)
					<button type="button" aria-pressed="{{ $diffView === $view ? 'true' : 'false' }}" wire:click="setDiffView('{{ $view->value }}')" @disabled($diffViews[$view->value] === 0 && $diffView !== $view)>{{ $view->label() }}<span class="n">{{ $diffViews[$view->value] }}</span></button>
				@endforeach
			</div>
			<span class="hint">
				@if ($diffView === \Src\Worktree\Enums\DiffViewEnum::UNCOMMITTED)
					{{ __('Tegenover de laatste commit') }}
				@elseif ($diffView === \Src\Worktree\Enums\DiffViewEnum::UNPUSHED)
					@if ($unpushedCount === 0){{ __('Alleen wat nog niet gecommit is') }}@else{{ trans_choice(':count commit met wijzigingen die nog niet op origin staan|:count commits met wijzigingen die nog niet op origin staan', $unpushedCount, ['count' => $unpushedCount]) }}{{ $diffViews['uncommitted'] > 0 ? ', ' . __('plus wat nog niet gecommit is') : '' }}@endif
				@else
					{{ __('Gecommit en niet gecommit samen, tegenover :main', ['main' => $worktree?->targetBranch ?? 'master']) }}
				@endif
			</span>
		</div>
	</div>

	<div class="diffsplit">
		<nav class="difffiles tree" aria-label="{{ __('Bestanden') }}">
			<div class="diff-total"><b class="grow">{{ __('Regels gewijzigd') }}</b><span class="plus">+{{ $diffAdded }}</span><span class="minus">−{{ $diffRemoved }}</span></div>
			@include('partials.diff-tree', ['nodes' => $diffTree, 'depth' => 0])
		</nav>
		<div class="resize-bar" data-resize="--diff-files" data-edge="end" data-min="140" data-max="520" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
		<div class="diffpatch stacked">
			@forelse ($diffFiles as $diffFile)
				<section id="{{ $diffFile['change']->anchor() }}" class="dfile" wire:key="dfile-{{ $diffView->value }}-{{ $diffFile['change']->anchor() }}-{{ $diffFile['fingerprint'] }}" x-data="{ open: @js(! $diffFile['isLarge'] && ! $diffFile['isGenerated']) && ! isViewed(@js($diffFile['change']->path)) }">
					<header class="dfile-head">
						<button class="icon-btn" type="button" x-on:click="open = ! open" :aria-expanded="open" aria-label="{{ __('In- of uitklappen') }}"><span class="dfile-chev" :class="{ shut: ! open }"><x-icon name="down" small/></span></button>
						<span class="tree-kind {{ $diffFile['change']->kind->tone() }}" title="{{ $diffFile['change']->kind->label() }}"><x-icon :name="$diffFile['change']->kind->icon()" small/></span>
						<span class="dfile-path">@if ($diffFile['change']->folder() !== '')<span class="muted">{{ str_replace('/', ' / ', $diffFile['change']->folder()) }} / </span>@endif<b>{{ $diffFile['change']->name() }}</b></span>
						<button class="icon-btn" type="button" x-on:click="navigator.clipboard.writeText(@js($diffFile['change']->path))" aria-label="{{ __('Pad kopiëren') }}" title="{{ __('Pad kopiëren') }}"><x-icon name="copy" small/></button>
						<span class="grow"></span>
						<span class="dfile-delta">@if ($diffFile['change']->added)<span class="plus">+{{ $diffFile['change']->added }}</span>@endif @if ($diffFile['change']->removed)<span class="minus">−{{ $diffFile['change']->removed }}</span>@endif</span>
						<label class="dfile-viewed"><input type="checkbox" :checked="isViewed(@js($diffFile['change']->path))" x-on:change="toggle(@js($diffFile['change']->path)); open = ! isViewed(@js($diffFile['change']->path))">{{ __('Bekeken') }}</label>
					</header>
					<div x-show="open">
						<livewire:diff-file :worktree-path="$worktree->path" :file="$diffFile['change']->path" :view="$diffView->value" :lines="$diffFile['change']->added + $diffFile['change']->removed" :wire:key="'diff-file-' . $diffView->value . '-' . $diffFile['change']->anchor() . '-' . $diffFile['fingerprint']"/>
					</div>
					@if ($diffFile['isGenerated'])
						<button class="dfile-large" type="button" x-show="! open" x-on:click="open = true">{{ __('Gegenereerd bestand, niet geladen. Tonen') }}</button>
					@elseif ($diffFile['isLarge'])
						<button class="dfile-large" type="button" x-show="! open" x-on:click="open = true">{{ __('Groot bestand, ingeklapt zodat de pagina snel blijft. Tonen') }}</button>
					@endif
				</section>
			@empty
				<p class="hint" style="padding:24px">{{ __('In dit overzicht is niets veranderd.') }}</p>
			@endforelse
		</div>
	</div>
</aside>
