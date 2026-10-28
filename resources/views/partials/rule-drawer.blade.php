<aside class="drawer" role="dialog" aria-labelledby="drawer-title">
	<div class="grab" aria-hidden="true"></div>
	<div class="dbar">
		<span class="kind">{{ __('Regelbestand') }}</span>
		<div class="dactions">
			<button class="btn icon ghost" type="button" wire:click="toggleActions" aria-label="{{ __('Acties') }}" aria-expanded="{{ $actionsOpen ? 'true' : 'false' }}"><x-icon name="meer"/></button>
			@if ($actionsOpen)
				<div class="actionmenu">
					@if ($file->isBehindCore())
						<button type="button" wire:click="askUpdateFromCore"><x-icon name="refresh" small/>{{ __('Bijwerken vanuit mtvtd-core') }}</button>
					@endif
					<button type="button" wire:click="startCopy"><x-icon name="copy" small/>{{ __('Overnemen in...') }}</button>
				</div>
			@endif
		</div>

		<button class="btn icon ghost close" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>
	<div class="dhead">
		<div class="row">
			<span class="chip {{ $file->origin->tone() }}">{{ $file->origin->label() }}</span>
			<span class="right">{{ __(':count regels', ['count' => $file->lines]) }}</span>
		</div>
		<h2 id="drawer-title"><span class="mono" style="font-size:19px">{{ $file->path }}</span></h2>
		<p class="sub">{{ $file->project }}</p>
	</div>
	<div class="tabs" role="tablist">
		@foreach (['Inhoud', 'Herkomst'] as $name)
			<button type="button" role="tab" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" wire:click="$set('drawerTab', '{{ $name }}')">{{ __($name) }}</button>
		@endforeach
	</div>
	<div class="dbody">
		@if ($done)<span class="note green"><x-icon name="check" small/><span>{{ $done }}</span></span>@endif
		@if ($tab === 'Inhoud')
			@if ($body)
				{!! $body !!}
				<span class="muted" style="font-size:12.5px">{{ __(':count regels, gelezen van schijf.', ['count' => $file->lines]) }}</span>
			@else
				<div class="empty">
					<span class="bubble"><x-icon name="file"/></span>
					<b>{{ __('Inhoud niet te lezen') }}</b>
					<span>{{ __('Het bestand staat niet meer op de plek waar het bord het vond.') }}</span>
				</div>
			@endif
		@else
			<dl class="facts">
				<div><dt>{{ __('Herkomst') }}</dt><dd>{{ $file->origin->label() }}@if ($file->coreFile)<small>{{ $file->linesBehind() > 0 ? __('core heeft :count regels meer', ['count' => $file->linesBehind()]) : __('Gelijk aan mtvtd-core') }}</small>@endif</dd></div>
				<div><dt>{{ __('Omvang') }}</dt><dd>{{ __(':count regels', ['count' => $file->lines]) }}<small>{{ __(':count woorden', ['count' => number_format($file->words, 0, ',', '.')]) }}</small></dd></div>
				<div><dt>{{ __('Groep') }}</dt><dd>{{ $file->group->label() }}</dd></div>
			</dl>

			<div class="field">
				<h3 class="eyebrow">{{ __('Hetzelfde bestand elders') }}</h3>
				@if ($elsewhere->isNotEmpty())
					<div class="linklist">
						@foreach ($elsewhere as $other)
							<div class="linkrow static">
								<span class="grow"><b class="mono" style="font-weight:500">{{ $other->project }}</b></span>
								<span class="chip {{ $other->origin->tone() }}">{{ $other->origin->label() }}</span>
								<span class="muted mono" style="font-size:12px">{{ __(':count regels', ['count' => $other->lines]) }}</span>
							</div>
						@endforeach
					</div>
				@else
					<div class="box">
						<div class="empty">
							<span class="bubble"><x-icon name="folder"/></span>
							<b>{{ __('Alleen hier') }}</b>
							<span>{{ __('Geen ander project heeft een bestand met deze naam.') }}</span>
						</div>
					</div>
				@endif
			</div>
		@endif
	</div>
</aside>
