<aside class="drawer" role="dialog" aria-labelledby="session-title">
	<div class="dbar">
		@if ($previous ?? null)
			<button class="back kind" type="button" wire:click="back"><x-icon name="back" small/>{{ $previous }}</button>
		@else
			<span class="kind">{{ __('Sessie') }} · {{ $session->project ?? __('geen project') }}</span>
		@endif

		<details class="menu" x-data="{ open: false }" :open="open" @click.outside="open = false">
			<summary class="icon-btn filled" aria-label="{{ __('Acties') }}" @click.prevent="open = ! open"><x-icon name="meer"/></summary>
			<div class="menu-pop" role="menu">
				<button type="button" role="menuitem" wire:click="startRename('{{ $session->uuid }}')">{{ __('Hernoemen') }}</button>
				<button type="button" role="menuitem" wire:click="pin('{{ $session->uuid }}', {{ $session->isPinned ? 'false' : 'true' }})">{{ $session->isPinned ? __('Losmaken, mag weer opgeruimd') : __('Vastzetten, nooit opruimen') }}</button>
			</div>
		</details>
		<button class="icon-btn" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead" style="padding-bottom:16px">
		<h2 id="session-title">{{ $session->name() }}</h2>
		<span class="sub">{{ $session->project ?? __('geen project') }} · {{ $session->branch ?? __('geen branch') }} · {{ $session->ticketKey() ?? __('geen ticket') }}</span>
	</div>

	<div class="dbody" style="gap:20px;padding:0 24px 24px">
		<div class="banner {{ $verdict['tone'] }}" style="border-radius:12px;padding:16px 18px">
			<div class="grow" style="display:flex;flex-direction:column;gap:2px;min-width:200px">
				<b style="font-size:15px">{{ $verdict['title'] }}</b>
				<span>{{ $verdict['text'] }}</span>
			</div>
		</div>

		@if ($traits !== [])
			<div class="list" style="border-radius:12px">
				@foreach ($traits as $trait)
					<div class="row" style="min-height:42px;padding:0 14px">
						<span style="flex:none;width:150px"><span @class(['label', 'green' => $trait->protects()])>{{ $trait->label() }}</span></span>
						<span class="hint" style="font-size:13px">{{ $trait->explanation() }}</span>
					</div>
				@endforeach
			</div>
		@endif

		<dl style="margin:0;display:grid;grid-template-columns:128px 1fr;gap:10px 14px;font-size:13.5px">
			<dt class="muted">{{ __('Laatst actief') }}</dt><dd style="margin:0">{{ $session->lastActiveLabel() }} · {{ trans_choice(':count dag gebruikt|:count dagen gebruikt', $session->activeDays, ['count' => $session->activeDays]) }}</dd>
			<dt class="muted">{{ __('Omvang') }}</dt><dd style="margin:0">{{ trans_choice(':count bericht|:count berichten', $session->promptCount, ['count' => $session->promptCount]) }} · {{ __(':size op schijf', ['size' => $size]) }}</dd>
			<dt class="muted">{{ __('Opgeleverd') }}</dt><dd style="margin:0">{{ $session->hasCommit ? __('minstens één commit') : __('geen enkele commit') }}</dd>
			<dt class="muted">{{ __('Map') }}</dt><dd style="margin:0" class="mono">{{ $session->cwd ?? __('onbekend') }}</dd>
		</dl>

		<section style="display:flex;flex-direction:column;gap:14px">
			<div style="display:flex;align-items:center;gap:10px">
				<h3 class="eyebrow grow">{{ __('Het gesprek') }}</h3>
				@if ($hiddenTurns > 0)
					<button class="btn text xs" type="button" wire:click="$set('wholeTranscript', true)">{{ __('Alles tonen') }}</button>
				@endif
			</div>

			@forelse ($transcript as $turn)
				<div class="say you" style="max-width:100%">
					<span class="who">{{ $turn->who }} · {{ $turn->at }}</span>
					<div class="bubble">{{ $turn->text }}</div>
				</div>
				@if ($hiddenTurns > 0 && $loop->iteration === 2)
					<span class="label" style="align-self:center;border-radius:999px;padding:4px 12px">{{ trans_choice(':count bericht ertussen|:count berichten ertussen', $hiddenTurns, ['count' => $hiddenTurns]) }}</span>
				@endif
			@empty
				<p class="hint" style="margin:0">{{ __('Geen berichten van jou in deze sessie.') }}</p>
			@endforelse
		</section>
	</div>

	<div class="dfoot">
		<button class="btn danger" type="button" wire:click="deleteSession('{{ $session->uuid }}')">{{ $confirmingDelete ? __('Zeker weten? Verwijderen') : __('Verwijderen') }}</button>
		<button class="btn" type="button" wire:click="resume('{{ $session->uuid }}')" @disabled($session->cwd === null)>{{ __('Verder in Claude Code') }}</button>
		<span class="mono muted" style="margin-left:auto;font-size:12px">{{ $size }}</span>
	</div>
</aside>
