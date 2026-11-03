<div class="scrim" style="z-index:65" wire:click="closeDiff"></div>
<aside class="drawer wide over diff-drawer" role="dialog" aria-labelledby="diff-title">
	<div class="resize-edge" data-resize="--diff-width" data-edge="start" data-min="560" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
	<div class="dbar">
		<span class="kind">{{ __('Wijziging') }} · {{ $card->ticketKey ?? $card->title }}</span>
		<button class="icon-btn" type="button" wire:click="closeDiff" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead diff-head">
		<div class="grow" style="min-width:0">
			<h2 id="diff-title" class="mono" style="font-size:18px">{{ basename($openDiffFile) }}</h2>
			<span class="meta"><span class="mono muted">{{ dirname($openDiffFile) }}</span><span class="plus">+{{ $diffAdded }}</span><span class="minus">−{{ $diffRemoved }}</span></span>
		</div>
		<div class="diff-views">
			<div class="seg" role="group" aria-label="{{ __('Welke wijzigingen') }}">
				@foreach (\Src\Worktree\Enums\DiffViewEnum::cases() as $view)
					<button type="button" aria-pressed="{{ $diffView === $view ? 'true' : 'false' }}" wire:click="setDiffView('{{ $view->value }}')" @disabled($diffViews[$view->value] === 0 && $diffView !== $view)>{{ $view->label() }}<span class="n">{{ $diffViews[$view->value] }}</span></button>
				@endforeach
			</div>
			<span class="hint">
				@if ($diffView === \Src\Worktree\Enums\DiffViewEnum::UNCOMMITTED)
					{{ __('Tegenover de laatste commit') }}
				@elseif ($diffView === \Src\Worktree\Enums\DiffViewEnum::UNPUSHED)
					{{ trans_choice(':count commit met wijzigingen die nog niet op origin staan|:count commits met wijzigingen die nog niet op origin staan', $unpushedCount, ['count' => $unpushedCount]) }}
				@else
					{{ __('Gecommit en niet gecommit samen, tegenover :main', ['main' => $worktree?->mainBranch ?? 'master']) }}
				@endif
			</span>
		</div>
	</div>

	<div class="diffsplit" style="border-top:1px solid var(--line)">
		<nav class="difffiles tree" aria-label="{{ __('Bestanden') }}">
			@include('partials.diff-tree', ['nodes' => $diffTree, 'depth' => 0])
		</nav>
		<div class="resize-bar" data-resize="--diff-files" data-edge="end" data-min="140" data-max="520" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
		<div class="diffpatch">
			<x-diff :hunks="$diffHunks" :file="$openDiffFile" :empty="__('In dit overzicht is aan dit bestand niets veranderd.')"/>
		</div>
	</div>
</aside>
