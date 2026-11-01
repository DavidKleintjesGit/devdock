<div class="split{{ $isStandard ? '' : ' has-page' }}">
	<nav class="index" aria-label="{{ __('Projecten') }}">
		<label class="search" style="flex:none;max-width:none">
			<x-icon name="search" small/>
			<span class="sr">{{ __('Zoek een project') }}</span>
			<input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Zoek een project') }}" autocomplete="off">
		</label>

		<a class="block" href="#" wire:click.prevent="selectProject('')" @if ($isStandard) aria-current="page" @endif>
			<b>{{ __('Standaard') }}</b>
			<small>{{ __('geldt voor elk project') }}</small>
		</a>

		<div class="index-group">
			<div class="eyebrow">
				<span>{{ __('Projecten · :count', ['count' => $index->count()]) }}</span>
				@if ($adjustCount > 0 || $onlyAdjust)
					<button class="tchip" type="button" style="height:26px;border-radius:999px;letter-spacing:0;text-transform:none;font-size:12px" aria-pressed="{{ $onlyAdjust ? 'true' : 'false' }}" wire:click="$toggle('onlyAdjust')">{{ __('Moet aangepast') }} <b style="color:var(--amber)">{{ $adjustCount }}</b></button>
				@endif
			</div>
			@foreach ($index as $item)
				<a href="#" wire:click.prevent="selectProject('{{ $item['name'] }}')" wire:key="wf-{{ $item['name'] }}" @if ($project === $item['name']) aria-current="page" @endif>
					<span class="dot {{ $item['tone'] }}"></span>
					<span class="grow ellipsis">{{ $item['name'] }}</span>
					@if ($item['note'])<span class="side-note {{ $item['noteTone'] }}">{{ $item['note'] }}</span>@endif
				</a>
			@endforeach
		</div>
	</nav>

	<div class="page">
		<header class="phead">
			<div class="inner">
				<div class="titles">
					@if (! $isStandard)
						<a class="back-link" href="{{ route('workflows') }}"><x-icon name="back" small/>{{ __('Projecten') }}</a>
					@endif
					<span class="eyebrow">{{ $isStandard ? __('Workflows') : __('Workflow') }}</span>
					<div class="head-title">
						<h1>{{ $isStandard ? __('Standaard') : $project }}</h1>
						@if (! $isStandard)
							@if ($check['broken'] > 0)
								<span class="label amber">{{ trans_choice(':count lens doet niet mee|:count lenzen doen niet mee', $check['broken'], ['count' => $check['broken']]) }}</span>
							@elseif ($check['warned'] > 0)
								<span class="label amber">{{ trans_choice(':count lens heeft geen eigen regels|:count lenzen hebben geen eigen regels', $check['warned'], ['count' => $check['warned']]) }}</span>
							@else
								<span class="label green">{{ __('alle paden bestaan') }}</span>
							@endif
						@endif
					</div>
					@if ($isStandard)
						<p style="margin:0;max-width:64ch;color:var(--fg-2)">{{ __('Wat elk project als standaard meekrijgt. Krijgt een project zijn workflow, dan neemt het dit over en kijkt DevDock meteen of de paden daar bestaan.') }}</p>
					@else
						<div class="chips" style="align-items:center;font-size:13px">
							<span class="hint">{{ $check['stack'] ? __('Herkend als :stack · regels gevonden in', ['stack' => $check['stack']]) : __('Regels gevonden in') }}</span>
							@forelse ($check['ruleFolders'] as $folder)<span class="path">{{ $folder }}</span>@empty<span class="hint">{{ __('niets') }}</span>@endforelse
							<button class="btn text xs" type="button" wire:click="$refresh">{{ __('Opnieuw zoeken') }}</button>
						</div>
					@endif
				</div>
			</div>
		</header>

		<main class="pbody">
			<div class="stack">
				@if ($saved)
					<div class="alert green"><x-icon name="check" small/><span class="grow">{{ $saved }}</span></div>
				@endif

				@if (! $isStandard && $check['broken'] > 0)
					<div class="alert">
						<x-icon name="alert" small/>
						<span class="grow"><b>{{ __('De standaard past hier niet helemaal') }}</b><br>{{ trans_choice(':count lens vindt hier niets om naar te kijken en doet niet mee tot je hem aanpast.|:count lenzen vinden hier niets om naar te kijken en doen niet mee tot je ze aanpast.', $check['broken'], ['count' => $check['broken']]) }}</span>
					</div>
				@endif

				<section class="section">
					<div class="section-head"><h2>{{ __('Zo loopt een taak') }}</h2></div>
					<div class="card" style="padding:24px 20px 20px">
						<div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr))">
							@foreach ($chain as $link)
								<div style="display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center">
									<div style="display:flex;align-items:center;width:100%">
										<span style="flex:1;height:2px;{{ $loop->first ? '' : 'background:var(--line-strong);margin-right:10px' }}"></span>
										<span style="width:30px;height:30px;border-radius:50%;border:2px solid {{ $loop->first ? 'var(--accent)' : 'var(--line-strong)' }};color:{{ $loop->first ? 'var(--accent-ink)' : 'var(--muted)' }};display:grid;place-items:center;font-size:12.5px;font-weight:600;flex:none">{{ $link['number'] }}</span>
										<span style="flex:1;height:2px;{{ $loop->last ? '' : 'background:var(--line-strong);margin-left:10px' }}"></span>
									</div>
									<b>{{ $link['label'] }}</b>
									@if ($isStandard)
										<span class="hint" style="padding:0 8px">{{ $link['text'] }}</span>
									@elseif ($link['number'] === 3)
										<span class="label teal">{{ trans_choice(':count lens|:count lenzen', $enabledCount, ['count' => $enabledCount]) }}</span>
									@endif
								</div>
							@endforeach
							<div style="grid-column:2 / 4;display:flex;justify-content:center;margin-top:10px">
								<span style="position:relative;display:block;width:100%;max-width:384px" title="{{ __('Niet schoon? Terug naar Bouwen. Hoogstens :max keer, daarna naar jou.', ['max' => $setting->maxRounds]) }}">
									<svg width="100%" height="34" viewBox="0 0 384 34" aria-hidden="true" style="display:block"><path d="M288 2 V12 Q288 24 276 24 H108 Q96 24 96 12 V8" fill="none" stroke="var(--amber-dot)" stroke-width="1.6" stroke-linecap="round"/><path d="M90 11 L96 3 L102 11" fill="none" stroke="var(--amber-dot)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
									<span style="position:absolute;left:50%;top:24px;transform:translate(-50%,-50%);padding:1px 8px;border:1.5px solid var(--amber-dot);border-radius:999px;background:var(--surface);color:var(--amber);font-size:11.5px;font-weight:600;line-height:1.4">{{ __('max :max×', ['max' => $setting->maxRounds]) }}</span>
								</span>
							</div>
						</div>
						<div class="loop" style="margin:18px -20px -20px;border-radius:0 0 14px 14px;border-width:1px 0 0">
							<x-icon name="loop" small/>
							<p style="margin:0;line-height:1.55"><b style="color:var(--amber)">{{ __('Niet schoon?') }}</b> {{ __('Vindt een lens iets, dan gaat de taak terug naar Bouwen en krijgt de agent de bevindingen mee. Dat gebeurt') }} <b style="color:var(--amber)">{{ trans_choice('hoogstens :count keer|hoogstens :count keer', $setting->maxRounds, ['count' => $setting->maxRounds]) }}</b>; {{ mb_strtolower($setting->afterRounds->explanation()) }}</p>
						</div>
					</div>
					@if (! $isStandard)<span class="hint">{{ __('Dit is de standaard.') }}</span>@endif
				</section>

				<section class="section">
					<div class="section-head">
						<h2 style="flex:none">{{ __('Lenzen') }}</h2>
						<span class="note" style="flex:1">{{ $isStandard ? __('doen mee in stap 3, zodra een gewijzigd bestand binnen hun paden valt') : $lensSummary }}</span>
						<button class="btn sm" type="button" wire:click="startLens">{{ $isStandard ? __('Lens toevoegen') : __('Eigen lens toevoegen') }}</button>
					</div>

					<div class="list">
						@if ($isStandard)
							@foreach ($lenses as $lens)
								<button class="row" type="button" style="flex-direction:column;align-items:stretch;gap:9px;padding:16px 20px" wire:click="editLens({{ $lens->lensId->id }})" wire:key="std-{{ $lens->lensId->id }}">
									<span style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap"><b style="font-size:15px">{{ $lens->name }}</b><span style="color:var(--fg-2)">{{ $lens->description }}</span></span>
									<span style="display:flex;gap:22px;flex-wrap:wrap;font-size:12.5px">
										<span class="chips" style="align-items:center"><span class="muted">{{ __('bekijkt') }}</span>@foreach ($lens->paths as $path)<span class="path">{{ $path }}</span>@endforeach</span>
										@if ($lens->rules !== [])<span class="chips" style="align-items:center"><span class="muted">{{ __('leest') }}</span>@foreach ($lens->rules as $rule)<span class="path" style="background:var(--accent-soft);color:var(--accent-ink)">{{ $rule }}</span>@endforeach</span>@endif
										@if ($lens->command)<span class="chips" style="align-items:center"><span class="muted">{{ __('draait') }}</span><span class="path">{{ $lens->command }}</span></span>@endif
									</span>
								</button>
							@endforeach
						@else
							@foreach ($lenses as $item)
								<div class="row" style="align-items:flex-start;gap:14px;padding:14px 20px" wire:key="lens-{{ $item['lens']->lensId->id }}">
									<button class="switch" type="button" role="switch" style="margin-top:2px" aria-checked="{{ $item['lens']->isEnabled ? 'true' : 'false' }}" aria-label="{{ __(':name aan', ['name' => $item['lens']->name]) }}" wire:click="toggleLens({{ $item['lens']->lensId->id }}, {{ $item['lens']->isEnabled ? 'false' : 'true' }})"></button>
									<button class="grow" type="button" style="border:0;background:transparent;text-align:left;padding:0;cursor:pointer;display:flex;flex-direction:column;gap:7px;font:inherit;color:inherit" wire:click="editLens({{ $item['lens']->lensId->id }})">
										<span style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
											<b style="font-size:15px;{{ $item['lens']->isEnabled ? '' : 'color:var(--muted)' }}">{{ $item['lens']->name }}</b>
											<span @class(['label', 'blue' => $item['isCustom']])>{{ $item['kind'] }}</span>
											@if ($item['check'] && $item['check']['state'] === 'off')<span class="label amber">{{ __('doet niet mee') }}</span>@endif
											@if ($item['lens']->isOwn())<span class="hint" style="font-size:13px">{{ $item['lens']->description }}</span>@endif
										</span>
										<span style="display:flex;gap:18px;flex-wrap:wrap;font-size:12.5px">
											@if ($item['showsPaths'])
												<span class="chips" style="align-items:center"><span class="muted">{{ __('bekijkt') }}</span>@foreach ($item['check']['paths'] ?? [] as $path)<span @class(['path', 'skip' => $path['count'] === 0])>{{ $path['pattern'] }}</span>@endforeach</span>
											@endif
											@if ($item['check'])
												<span class="chips" style="align-items:center"><span class="muted">{{ __('leest') }}</span>@forelse ($item['check']['reads'] as $file)<span class="path" style="background:var(--accent-soft);color:var(--accent-ink)">{{ $file }}</span>@empty<span class="hint">{{ __('niets') }}</span>@endforelse @if ($item['check']['isFallback'] && $item['check']['reads'] !== [])<span class="hint" style="color:var(--amber)">{{ __('geen eigen regelbestand') }}</span>@endif</span>
												@if ($item['lens']->command && ($item['lens']->command !== $item['lens']->standardCommand || ! $item['check']['commandWorks']))
													<span class="chips" style="align-items:center"><span class="muted">{{ __('draait') }}</span><span @class(['path', 'deny' => ! $item['check']['commandWorks']])>{{ $item['lens']->command }}</span></span>
												@endif
											@endif
										</span>
									</button>
									<x-icon name="chev" small/>
								</div>
							@endforeach
						@endif
					</div>
					@if (! $isStandard)
						<span class="hint">{{ __('Bij een lens uit de standaard staat alleen wat hij hier leest. Wat hij bekijkt en draait komt er pas bij als het van de standaard afwijkt.') }}</span>
					@endif
				</section>

				<section class="section">
					<div class="section-head"><h2>{{ __('Drempel') }}</h2></div>
					@if ($isStandard || $setting->hasOwnThreshold || $editingThreshold)
						<div class="list">
							<div class="row" style="gap:20px;flex-wrap:wrap;padding:16px 20px">
								<span class="title" style="flex:1 1 260px"><b>{{ __('Hoogstens') }}</b><small>{{ __('Elke ronde draait opnieuw op de nieuwste commit.') }}</small></span>
								<span class="seg" role="group" aria-label="{{ __('Aantal rondes') }}">
									@foreach ($roundChoices as $choice)
										<button type="button" aria-pressed="{{ $setting->maxRounds === $choice ? 'true' : 'false' }}" wire:click="setThreshold({{ $choice }}, '{{ $setting->afterRounds->value }}')">{{ trans_choice(':count ronde|:count rondes', $choice, ['count' => $choice]) }}</button>
									@endforeach
								</span>
							</div>
							<div class="row" style="gap:20px;flex-wrap:wrap;padding:16px 20px">
								<span class="title" style="flex:1 1 260px"><b>{{ __('Daarna') }}</b><small>{{ $setting->afterRounds->explanation() }}</small></span>
								<span class="seg" role="group" aria-label="{{ __('Wat er na de drempel gebeurt') }}">
									@foreach ($afterChoices as $choice)
										<button type="button" aria-pressed="{{ $setting->afterRounds === $choice ? 'true' : 'false' }}" wire:click="setThreshold({{ $setting->maxRounds }}, '{{ $choice->value }}')">{{ $choice->label() }}</button>
									@endforeach
								</span>
							</div>
						</div>
						@if (! $isStandard)
							<button class="btn text xs" type="button" style="align-self:flex-start" wire:click="resetThreshold">{{ __('Terug naar de standaard') }}</button>
						@endif
					@else
						<div class="list">
							<div class="row" style="padding:14px 20px">
								<span class="grow">{{ __('Hoogstens :max rondes, daarna :after', ['max' => $setting->maxRounds, 'after' => mb_strtolower($setting->afterRounds->label())]) }}</span>
								<span class="label">{{ __('standaard') }}</span>
								<button class="btn xs" type="button" wire:click="$set('editingThreshold', true)">{{ __('Aanpassen voor dit project') }}</button>
							</div>
						</div>
					@endif
				</section>

				<section class="section">
					<div class="section-head">
						<h2 style="flex:none">{{ $isStandard ? __('Wat de agent altijd meekrijgt') : __('Wat de agent meekrijgt') }}</h2>
						@if ($isStandard)<span class="note">{{ __('een project kan hier iets aan toevoegen, maar niets aan veranderen') }}</span>@endif
					</div>

					@if ($isStandard)
						<div class="card" style="padding:18px 20px;display:flex;flex-direction:column;gap:14px">
							<label class="eyebrow" for="context-standing">{{ __('Altijd') }}</label>
							<textarea id="context-standing" class="textarea" rows="4" wire:model="context.standing"></textarea>
							<div style="display:flex;flex-direction:column;border-top:1px solid var(--line)">
								@foreach ($steps as $step)
									<details style="border-bottom:1px solid var(--line)" wire:key="ctx-{{ $step->value }}">
										<summary style="display:flex;align-items:center;gap:10px;padding:12px 0;cursor:pointer;list-style:none">
											<span class="muted" style="width:16px">+</span>
											<b style="width:160px">{{ $step->label() }}</b>
											<span class="hint ellipsis grow">{{ $context[$step->value] ?? '' }}</span>
										</summary>
										<textarea class="textarea" rows="3" style="margin-bottom:12px" wire:model="context.{{ $step->value }}" aria-label="{{ $step->label() }}"></textarea>
									</details>
								@endforeach
							</div>
							<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
								<button class="btn primary" type="button" style="height:36px" wire:click="saveContext">{{ __('Opslaan') }}</button>
								<span class="hint">{{ __('Landt in elke werkkopie bij de volgende ronde.') }}</span>
							</div>
						</div>
					@else
						<div class="list">
							<div class="row" style="padding:14px 20px">
								<span class="title grow"><b>{{ __('Van de standaard') }}</b><small>{{ __('Altijd, en een regel per stap. Geldt voor elk project.') }}</small></span>
								<button class="btn xs" type="button" wire:click="selectProject('')">{{ __('Bekijken in de standaard') }}</button>
							</div>
							<div style="display:flex;flex-direction:column;gap:10px;padding:16px 20px;border-top:1px solid var(--line)">
								<span class="title"><b>{{ __('Alleen voor :project', ['project' => $project]) }}</b><small class="hint">{{ __('Komt achter de tekst uit de standaard. Het vervangt er niets van.') }}</small></span>
								<textarea class="textarea" rows="4" wire:model="projectContext" placeholder="{{ __('Nog niets toegevoegd.') }}"></textarea>
								<button class="btn primary" type="button" style="height:36px;align-self:flex-start" wire:click="saveProjectContext">{{ __('Opslaan') }}</button>
							</div>
						</div>
					@endif
				</section>
			</div>
		</main>
	</div>

	@if ($lensDrawer)
		@include('partials.lens-drawer', $lensDrawer)
	@endif

	@if ($creatingLens)
		@include('partials.lens-new')
	@endif
</div>
