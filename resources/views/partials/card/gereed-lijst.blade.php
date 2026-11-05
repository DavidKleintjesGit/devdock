@if ($readiness)
	<section class="dsec">
		<div class="headrow changes-head">
			<h3 class="eyebrow grow">{{ __('Klaar voor werk') }}</h3>
			<span class="hint">{{ __('nagekeken :when', ['when' => $readiness->checkedAt->diffForHumans()]) }}@if ($readiness->projectKind) · {{ $readiness->projectKind->label() }}@endif</span>
			<x-busy-button action="checkSetupAgain" class="btn text xs" :busy="__('Nakijken...')">{{ __('Opnieuw nakijken') }}</x-busy-button>
			@if ($readiness->canRepairAny() && ! $repairState?->isRunning)
				<x-busy-button action="repairSetup" class="btn sm" :busy="__('Starten...')">{{ __('Alles herstellen') }}</x-busy-button>
			@endif
		</div>
		<div class="list">
			@foreach ($readiness->setupChecks as $setupCheck)
				<div class="kv" wire:key="setup-{{ $setupCheck->setupCheck->value }}"><div class="v">
					<span class="sdot {{ $setupCheck->state->tone() }}"></span>
					<span class="grow" style="font-family:inherit;display:flex;flex-direction:column;gap:2px"><b>{{ $setupCheck->setupCheck->label() }}</b><span class="hint">{{ $setupCheck->detail }}</span></span>
					@if ($setupCheck->repair && ! $setupCheck->isReady())
						<button class="btn sm" type="button" wire:click="repairSetup('{{ $setupCheck->setupCheck->value }}')" @disabled(! $setupCheck->canRepair() || $repairState?->isRunning)>{{ __('Herstellen') }} · {{ $setupCheck->repair }}</button>
					@endif
				</div></div>
			@endforeach
		</div>
		@if ($repairState && ! $repairState->isRunning)
			<details class="fold" wire:ignore.self wire:key="repair-log-{{ $repairState->finishedAt?->getTimestamp() }}">
				<summary><x-icon name="chev" small/><b class="grow">{{ __('Log van de laatste herstelactie') }}</b><span class="hint" @if ($repairState->failure) style="color:var(--red)" @endif>{{ $repairState->failure ? __('vastgelopen') : __('klaar') }} · {{ $repairState->finishedAt?->diffForHumans() }}</span></summary>
				<div class="foldbody" style="max-height:360px;overflow:auto">
					@if ($repairState->failure)<p class="hint" style="margin:0 0 8px;color:var(--red);overflow-wrap:anywhere">{{ $repairState->failure }}</p>@endif
					<pre class="mono" style="margin:0;font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere;color:var(--fg-2)">{{ $repairState->logTail() }}</pre>
				</div>
			</details>
		@endif
	</section>
@endif
