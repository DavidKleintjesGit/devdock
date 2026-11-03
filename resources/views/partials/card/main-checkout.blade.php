{{-- A branch that is checked out in the project folder itself, and the way out to a working copy of its own. --}}
<section class="dsec" x-data="{ hidden: false }" x-show="! hidden">
	<div class="list">
		<div class="kv">
			<div class="v">
				<span class="sdot busy" style="animation:none"></span>
				<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ __('Deze branch staat in je hoofdcheckout') }}</b><span class="hint"><span class="mono" style="font-size:12px">{{ $mainCheckout['path'] }}</span> {{ __('staat op') }} <span class="mono" style="font-size:12px">{{ $card->branch }}</span>. {{ __('De agent, de lenzen en de tests werken pas los van jouw werk in een eigen werkkopie.') }}</span></span>
			</div>
			<ol style="margin:0 0 0 21px;padding:0 0 0 16px;list-style:decimal;display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--fg-2)">
				<li>{{ __('De hoofdcheckout gaat terug naar :main', ['main' => $card->mainBranch ?? 'master']) }}</li>
				<li>{{ __('Een werkkopie in') }} <span class="mono" style="font-size:12px">{{ $mainCheckout['folder'] }}</span>{{ __(', met een eigen site') }}</li>
				<li>{{ __('Een eigen testdatabase') }}</li>
			</ol>
			@if ($mainCheckout['uncommitted'] === [])
				<span class="v" style="margin-top:4px"><x-busy-button action="moveToOwnWorkingCopy" class="btn sm primary" :busy="__('Verhuizen...')">{{ __('Verhuizen naar eigen werkkopie') }}</x-busy-button></span>
			@endif
		</div>

		@if ($mainCheckout['uncommitted'] !== [])
			<div class="kv">
				<div class="v">
					<span class="sdot conflict"></span>
					<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ trans_choice(':count bestand niet gecommit in de hoofdcheckout|:count bestanden niet gecommit in de hoofdcheckout', count($mainCheckout['uncommitted']), ['count' => count($mainCheckout['uncommitted'])]) }}</b><span class="hint">{{ __('Verhuizen zou ze op deze branch achterlaten. Kies eerst wat ermee gebeurt.') }}</span></span>
				</div>
				<ul style="margin:0 0 0 21px;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px">
					@foreach ($mainCheckout['uncommitted'] as $uncommittedFile)
						<li class="mono" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $uncommittedFile }}</li>
					@endforeach
				</ul>
				<span class="v" style="margin-top:4px;flex-wrap:wrap">
					<x-busy-button action="moveToOwnWorkingCopy(true)" class="btn sm" :busy="__('Verhuizen...')">{{ __('Meenemen naar de werkkopie') }}</x-busy-button>
					@if ($canCommitHere)
						<button class="btn sm" type="button" x-on:click="document.getElementById('commit-title')?.focus()">{{ __('Eerst committen') }}</button>
					@endif
					<button class="btn text sm" type="button" x-on:click="hidden = true">{{ __('Laten staan') }}</button>
				</span>
			</div>
		@endif
	</div>
</section>
