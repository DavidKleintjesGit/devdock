<aside class="diffpanel" role="dialog" aria-label="{{ __('Wijziging lezen') }}">
	<div class="dbar">
		<button class="back" type="button" wire:click="closeDiff"><x-icon name="back" small/><span>{{ $card->ticketKey ?? $card->title }}</span></button>
		<button class="btn icon ghost close" type="button" wire:click="closeDiff" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<h2 class="mono">{{ basename($openDiffFile) }}</h2>
		<div class="row">
			<span class="muted mono">{{ dirname($openDiffFile) }}</span>
			<span class="plus">+{{ $diffAdded }}</span>
			<span class="minus">−{{ $diffRemoved }}</span>
		</div>
	</div>

	<div class="diffsplit">
		<div class="difffiles">
			<div class="fieldhead">
				<h3 class="eyebrow">{{ __('Bestanden') }}</h3>
				<span class="muted">{{ count($diffFiles) }}</span>
			</div>
			<div class="difflist">
				@foreach ($diffFiles as $file)
					<button class="dfile {{ $openDiffFile === $file ? 'on' : '' }}" type="button" wire:click="openDiff('{{ $file }}')" wire:key="dfile-{{ $loop->index }}">
						<span class="nm">
							<b class="mono">{{ basename($file) }}</b>
							<small class="mono">{{ dirname($file) }}</small>
						</span>
					</button>
				@endforeach
			</div>
		</div>

		<div class="diffpatch">
			@forelse ($diffHunks as $hunk)
				<div class="hunk">
					<div class="hunkhead mono">{{ $hunk->header }}</div>
					<table class="lines">
						@foreach ($hunk->lines as $line)
							<tr class="{{ $line->kind }}">
								<td class="n">{{ $line->oldNumber }}</td>
								<td class="n">{{ $line->newNumber }}</td>
								<td class="s">{{ $line->sign() }}</td>
								<td class="t">{{ $line->text }}</td>
							</tr>
						@endforeach
					</table>
				</div>
			@empty
				<div class="prose empty-prose">{{ __('Git heeft over dit bestand niets te melden tegenover de hoofdlijn.') }}</div>
			@endforelse
		</div>
	</div>

	<div class="dfoot">
		<span class="hint">{{ __('Tegenover de hoofdlijn, gecommit en ongecommit samen.') }}</span>
	</div>
</aside>
