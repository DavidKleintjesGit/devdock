<aside class="drawer" role="dialog" aria-labelledby="drawer-title">
	<div class="grab" aria-hidden="true"></div>
	<div class="dbar">
		<span class="kind">{{ __('Skill') }}</span>
		<div class="dactions">
			<button class="btn icon ghost" type="button" wire:click="toggleActions" aria-label="{{ __('Acties') }}" aria-expanded="{{ $actionsOpen ? 'true' : 'false' }}"><x-icon name="meer"/></button>
			@if ($actionsOpen)
				<div class="actionmenu">
					<button type="button" wire:click="startCopy"><x-icon name="copy" small/>{{ __('Overnemen in...') }}</button>
				</div>
			@endif
		</div>

		<button class="btn icon ghost close" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>
	<div class="dhead">
		<div class="row">
			<span class="chip {{ $skill->isEditable() ? 'teal' : 'gray' }}">{{ $skill->scope->label() }}</span>
			<span class="right">{{ __(':count regels', ['count' => $skill->lines]) }}</span>
		</div>
		<h2 id="drawer-title"><span class="mono">{{ $skill->name }}</span></h2>
	</div>
	<div class="dbody">
		@if ($done)<span class="note green"><x-icon name="check" small/><span>{{ $done }}</span></span>@endif
		<p style="margin:0;color:var(--fg-2);font-size:15px">{{ $skill->description }}</p>

		<dl class="facts">
			<div><dt>{{ __('Waar') }}</dt><dd>{{ $skill->placeLabel() }}</dd></div>
			<div><dt>{{ __('Bestand') }}</dt><dd class="mono" style="font-weight:500">{{ $skill->location() }}</dd></div>
		</dl>

		@if (! $skill->isEditable())
			<div class="readonly-note"><x-icon name="lock" small/><span>{{ __('Deze skill komt uit een plugin en kun je hier niet aanpassen.') }}</span></div>
		@endif

		<div class="field">
			<h3 class="eyebrow">{{ __('Inhoud') }}</h3>
			@if ($body)
				{!! $body !!}
			@else
				<div class="empty">
					<span class="bubble"><x-icon name="file"/></span>
					<b>{{ __('Inhoud niet te lezen') }}</b>
					<span>{{ __('Het bestand staat niet meer op de plek waar het bord het vond.') }}</span>
				</div>
			@endif
		</div>
	</div>
</aside>
