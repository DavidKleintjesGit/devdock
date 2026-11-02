<div class="scrim" style="z-index:65" wire:click="closeDiff"></div>
<aside class="drawer wide over diff-drawer" role="dialog" aria-labelledby="diff-title">
	<div class="resize-edge" data-resize="--diff-width" data-edge="start" data-min="560" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
	<div class="dbar">
		<span class="kind">{{ __('Wijziging') }} · {{ $card->ticketKey ?? $card->title }}</span>
		<span class="hint">{{ __(':number van :total', ['number' => $diffPosition, 'total' => count($diffFiles)]) }}</span>
		<button class="icon-btn" type="button" wire:click="closeDiff" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<h2 id="diff-title" class="mono" style="font-size:18px">{{ basename($openDiffFile) }}</h2>
		<span class="meta"><span class="mono muted">{{ dirname($openDiffFile) }}</span><span class="plus">+{{ $diffAdded }}</span><span class="minus">−{{ $diffRemoved }}</span><span class="label">{{ __('Tegenover :main', ['main' => $worktree?->mainBranch ?? 'master']) }}</span></span>
	</div>

	<div class="diffsplit" style="border-top:1px solid var(--line)">
		<nav class="difffiles tree" aria-label="{{ __('Bestanden') }}">
			@include('partials.diff-tree', ['nodes' => $diffTree, 'depth' => 0])
		</nav>
		<div class="resize-bar" data-resize="--diff-files" data-edge="end" data-min="140" data-max="520" aria-hidden="true" title="{{ __('Sleep om breder of smaller te maken') }}"></div>
		<div class="diffpatch">
			<x-diff :hunks="$diffHunks" :file="$openDiffFile" :empty="__('Git heeft over dit bestand niets te melden tegenover de hoofdlijn.')"/>
		</div>
	</div>

	<div class="dfoot">
		<span class="hint">{{ __('Gecommit en ongecommit samen, tegenover de hoofdlijn.') }}</span>
		@if ($nextDiffFile)
			<button class="btn primary" type="button" wire:click="openDiff('{{ $nextDiffFile }}')">{{ __('Volgend bestand') }}<x-icon name="arrow" small/></button>
		@endif
	</div>
</aside>
