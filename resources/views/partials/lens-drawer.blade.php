<div class="scrim" wire:click="closeLens"></div>
<aside class="drawer panel-drawer" role="dialog" aria-labelledby="lens-title">
	<div class="dbar">
		<span class="kind">{{ __('Lens') }} · {{ $project ?: __('standaard') }}</span>
		<button class="icon-btn" type="button" wire:click="closeLens" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
	</div>

	<div class="dhead">
		<span class="meta">
			@if ($lens->isOwn())<span class="label blue">{{ __('eigen lens') }}</span>@elseif ($lens->isCustomized())<span class="label blue">{{ __('aangepast voor dit project') }}</span>@elseif ($project)<span class="label">{{ __('standaard') }}</span>@endif
		</span>
		<h2 id="lens-title">{{ $lens->name }}</h2>
		@if ($project && ! $lens->isOwn())
			<p class="sub" style="margin:0">{{ $lens->description }}</p>
			<p class="hint" style="margin:0">{{ __('Dit komt uit de standaard en geldt in elk project.') }} <a href="#" wire:click.prevent="selectProject('')">{{ __('Aanpassen in de standaard') }}</a></p>
		@endif
	</div>

	<div class="dbody">
		@if (! $project || $lens->isOwn())
			<section class="dsec">
				<label class="eyebrow" for="lens-description">{{ __('Waar hij op let') }}</label>
				<textarea id="lens-description" class="textarea" rows="3" wire:model="lensDescription"></textarea>
			</section>
		@endif

		<section class="dsec">
			<h3 class="eyebrow">{{ __('Bekijkt deze bestanden') }}</h3>
			<div class="list">
				@foreach ($paths as $index => $path)
					<div class="row" style="min-height:44px" wire:key="path-{{ $index }}">
						@if ($path['count'] === null)
							<span class="checkpath"><span class="muted">·</span></span>
						@elseif ($path['count'] > 0)
							<span class="checkpath"><span class="ok">✓</span></span>
						@else
							<span class="checkpath"><span class="skip">–</span></span>
						@endif
						<span class="path">{{ $path['pattern'] }}</span>
						<span class="grow hint">{{ $path['count'] === null ? '' : ($path['count'] > 0 ? trans_choice(':count bestand|:count bestanden', $path['count'], ['count' => $path['count']]) : __('bestaat hier niet, overgeslagen')) }}{{ $path['isStandard'] && $project ? ' · ' . __('standaard') : '' }}</span>
						<button class="icon-btn" type="button" style="width:28px;height:28px" wire:click="removePath({{ $index }})" aria-label="{{ __('Pad weghalen') }}"><x-icon name="x" small/></button>
					</div>
				@endforeach
				<div class="row" style="gap:8px;padding:10px 16px">
					<label class="sr" for="lens-path">{{ __('Pad toevoegen') }}</label>
					<input id="lens-path" class="input mono" type="text" wire:model.live.debounce.400ms="newPath" wire:keydown.enter="addPath" placeholder="database/factories/**" style="height:34px">
					<button class="btn sm" type="button" wire:click="addPath">{{ __('Toevoegen') }}</button>
				</div>
			</div>
			@if ($newPathCount !== null)<span class="hint">{{ trans_choice(':count bestand valt eronder|:count bestanden vallen eronder', $newPathCount, ['count' => $newPathCount]) }}</span>@endif
		</section>

		<section class="dsec">
			<h3 class="eyebrow">{{ __('Leest deze regels') }}</h3>
			@if ($project)
				<p class="hint">{{ __('DevDock vond deze regelbestanden in het project. Vink aan wat deze lens moet lezen.') }}</p>
				<div class="list">
					@forelse ($rules as $rule)
						<label class="tick row" style="min-height:42px" wire:key="rule-{{ $rule['file'] }}">
							<input type="checkbox" @checked($rule['isPicked']) wire:click="toggleRule('{{ $rule['file'] }}')">
							<span class="mark"></span>
							<span class="mono grow">{{ $rule['file'] }}</span>
							@if ($rule['pattern'])<span class="hint">{{ __('past op :pattern', ['pattern' => $rule['pattern']]) }}</span>@endif
						</label>
					@empty
						<div class="row hint">{{ __('Geen regelbestanden gevonden; de lens leest dan CLAUDE.md of AGENTS.md.') }}</div>
					@endforelse
				</div>
			@else
				<p class="hint">{{ __('Patronen; in elk project kijkt DevDock welke bestanden erop passen.') }}</p>
				<div class="chips">
					@foreach ($lensRules as $rule)<span class="tchip on">{{ $rule }}<button class="icon-btn" type="button" style="width:18px;height:18px" wire:click="toggleRule('{{ $rule }}')" aria-label="{{ __('Weghalen') }}"><x-icon name="x" small/></button></span>@endforeach
				</div>
			@endif
		</section>

		<section class="dsec">
			<label class="eyebrow" for="lens-command">{{ __('Draait') }}</label>
			@if ($project && ! $lens->isOwn())
				<span class="hint">{{ $lens->standardCommand ? __('Standaard is :command.', ['command' => $lens->standardCommand]) : __('Standaard draait hij niets.') }}</span>
			@endif
			<div style="display:flex;gap:8px">
				<input id="lens-command" class="input mono" type="text" wire:model="lensCommand" placeholder="{{ __('geen commando') }}">
				@if ($project && ! $lens->isOwn())<button class="btn sm" type="button" wire:click="resetCommand">{{ __('Terugzetten') }}</button>@endif
			</div>
		</section>
	</div>

	<div class="dfoot">
		@if ($project && $lens->isCustomized())
			<button class="btn text" type="button" wire:click="resetLens">{{ __('Alles terug naar standaard') }}</button>
		@endif
		<span class="hint"></span>
		<button class="btn primary" type="button" wire:click="saveLens">{{ __('Klaar') }}</button>
	</div>
</aside>
