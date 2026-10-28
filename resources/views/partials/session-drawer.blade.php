<aside class="drawer" role="dialog" aria-labelledby="drawer-title">
	<div class="grab" aria-hidden="true"></div>

	<div class="dbar">
		@if ($previous)
			<button class="back" type="button" wire:click="back"><x-icon name="back" small/><span>{{ $previous }}</span></button>
		@else
			<span class="kind">{{ __('Sessie') }}</span>
		@endif

		<div class="dactions">
			<button class="btn icon ghost" type="button" wire:click="toggleActions" aria-label="{{ __('Acties') }}" aria-expanded="{{ $actionsOpen ? 'true' : 'false' }}"><x-icon name="meer"/></button>
			@if ($actionsOpen)
				<div class="actionmenu">
					<button type="button" wire:click="startRename('{{ $session->uuid }}')"><x-icon name="pencil" small/>{{ __('Hernoemen') }}</button>
					<button type="button" class="danger" wire:click="deleteSession('{{ $session->uuid }}')"><x-icon name="alert" small/>{{ $confirmingDelete ? __('Weet je het zeker?') : __('Verwijderen') }}</button>
				</div>
			@endif
		</div>

		<button class="btn icon ghost close" type="button" wire:click="closeDrawer" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<div class="row">
			<span class="mono" style="color:var(--accent-ink)">{{ $session->uuid }}</span>
			<span class="chip {{ $session->status->tone() }}">{{ $session->status->label() }}</span>
			@if ($session->mismatchedTicket())<span class="chip red">{{ __('Naam ≠ :ticket', ['ticket' => $session->mismatchedTicket()]) }}</span>@endif
			<span class="right">{{ $session->lastActiveLabel() }}</span>
		</div>
		<h2 id="drawer-title"><span class="{{ $session->title ? '' : 'muted' }}">{{ $session->name() }}</span></h2>
		<p class="sub">{{ $session->project ?? __('Zonder project') }} · {{ __(':count prompts', ['count' => $session->promptCount]) }} · {{ $session->sizeLabel() }}</p>
	</div>

	<div class="tabs" role="tablist">
		@foreach (['Overzicht', 'Rondes', 'Transcript'] as $name)
			<button type="button" role="tab" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" wire:click="setDrawerTab('{{ $name }}')">{{ __($name) }}</button>
		@endforeach
	</div>

	<div class="dbody">
		@if ($tab === 'Overzicht')
			<dl class="facts">
				<div><dt>{{ __('Project') }}</dt><dd>{{ $session->project ?? __('Zonder project') }}<small class="mono">{{ $projectPath ?? $session->folder }}</small></dd></div>
				<div><dt>{{ __('Branch') }}</dt><dd>{!! $session->branch ? '<span class="mono">' . e($session->branch) . '</span>' : e(__('Geen branch')) !!}@if ($session->ticketKey())<small>{{ __('Ticket uit de branch: :ticket', ['ticket' => $session->ticketKey()]) }}</small>@endif</dd></div>
				<div><dt>{{ __('Omvang') }}</dt><dd>{{ __(':count prompts', ['count' => $session->promptCount]) }}<small>{{ __(':size op schijf', ['size' => $session->sizeLabel()]) }}</small></dd></div>
				<div><dt>{{ __('Status') }}</dt><dd>{{ $session->status->label() }}<small>{{ __('Laatst actief: :when', ['when' => $session->lastActiveLabel()]) }}</small></dd></div>
			</dl>
			<div class="resume">
				<b>{{ __('Verder in Claude Code') }}</b>
				<span style="color:var(--fg-2);font-size:13.5px">{{ __('Dit opent de sessie buiten het bord. Claude Code start in de projectmap en pakt het gesprek op waar het ophield.') }}</span>
				<div class="code mono">cd {{ $projectPath ?? '.' }}
claude --resume {{ $session->uuid }}</div>
			</div>
		@elseif ($tab === 'Rondes')
			<div class="empty">
				<span class="bubble"><x-icon name="workflows"/></span>
				<b>{{ __('Nog geen rondes') }}</b>
				<span>{{ __('Deze sessie heeft nog geen ronde door de lenzen gehad.') }}</span>
			</div>
		@else
			@if ($transcript->isNotEmpty())
				<ol class="turns">
					@foreach ($transcript as $turn)
						<li class="turn you">
							<span class="who mono">{{ __('jij') }}</span>
							<span class="what"><span>{{ $turn['text'] }}</span><span class="t mono">{{ $turn['at'] }}</span></span>
						</li>
					@endforeach
				</ol>
				<span class="muted" style="font-size:12.5px">{{ __('De eerste :shown van :total prompts. Het bord leest ze uit het sessiebestand en schrijft er niets in.', ['shown' => $transcript->count(), 'total' => $session->promptCount]) }}</span>
			@else
				<div class="empty">
					<span class="bubble"><x-icon name="chat"/></span>
					<b>{{ __('Nog geen transcript') }}</b>
					<span>{{ __('Het gesprek van deze sessie is nog niet ingeladen.') }}</span>
				</div>
			@endif
		@endif
	</div>
</aside>
