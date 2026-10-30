<div class="page">
	<header class="pagebar">
		<div class="logo m-logo" aria-hidden="true">D</div>
		<h1>{{ __('Workflows') }}</h1>
		<span class="count">{{ __(':total onderweg · :lenses lenzen · drempel :max rondes', ['total' => $total, 'lenses' => $lenses->count(), 'max' => $maxRounds]) }}</span>
		<x-page-actions/>
	</header>

	<div class="toolbar">
		<div class="tb-filters">
			<x-project-picker
				:options="$projectOptions"
				:selected="$projects"
				:total="$projectTotal"
				:query="$projectQuery"
				query-property="projectQuery"
				toggle="toggleProject"
				clear="clearProjects"
			/>

			<div class="seg" role="group" aria-label="{{ __('Stap') }}">
				<button type="button" aria-pressed="{{ $filter === '' ? 'true' : 'false' }}" wire:click="setFilter('')">{{ __('Alles') }} <span class="n">{{ $total }}</span></button>
				@foreach ($steps as $case)
					<button type="button" aria-pressed="{{ $filter === $case->value ? 'true' : 'false' }}" wire:click="setFilter('{{ $case->value }}')">{{ $case->label() }} <span class="n">{{ $perStep[$case->value] }}</span></button>
				@endforeach
			</div>

			@if ($stuck)
				<button class="btn sm" type="button" aria-pressed="{{ $filter === 'stuck' ? 'true' : 'false' }}" wire:click="setFilter('stuck')"><span class="dot red"></span>{{ __('Vastgelopen') }} <span class="n">{{ $stuck }}</span></button>
			@endif
		</div>
	</div>

	<div class="content">
		<section class="section">
			<div class="section-head">
				<h2>{{ __('Zo loopt een taak') }}</h2>
				<span class="side-note">{{ __('Een ronde draait bij elke oplevering, niet bij elke prompt.') }}</span>
			</div>
			<ol class="chain box">
				@foreach ($steps as $case)
					<li class="step">
						<span class="n">{{ $case->number() }}</span>
						<b>{{ $case->label() }}</b>
						<p>{{ $case->explanation() }}</p>
					</li>
				@endforeach
			</ol>
		</section>

		<section class="section">
			<div class="section-head">
				<h2>{{ __('Nu bezig') }}</h2>
				<span class="side-note">{{ trans_choice(':count taak|:count taken', $workflows->count(), ['count' => $workflows->count()]) }}</span>
			</div>
			@if ($workflows->isNotEmpty())
				<div class="box list">
					@foreach ($workflows as $workflow)
						<a class="wrow" wire:key="wf-{{ $workflow['card']->id }}" href="{{ route('board') }}?card={{ urlencode($workflow['card']->id) }}">
							<span class="wname">
								<b>{{ $workflow['card']->title }}</b>
								<small class="mono">{{ $workflow['card']->ticketKey ?? __('Zonder ticket') }} · {{ $workflow['card']->project ?? __('Zonder project') }}{{ $workflow['round']?->commitSha ? ' · ' . $workflow['round']->commitSha : '' }}</small>
							</span>
							<ol class="hsteps">
								@foreach ($steps as $case)
									<li class="{{ $case === $workflow['step'] ? 'now' : ($case->comesBefore($workflow['step']) ? 'done' : '') }}">
										@unless ($loop->first)<span class="bar {{ $case->comesBefore($workflow['step']) || $case === $workflow['step'] ? 'on' : '' }}"></span>@endunless
										<span class="n"></span>
									</li>
								@endforeach
							</ol>
							<span class="wlens">
								<span class="lensdots">
									@foreach ($workflow['round']?->results ?? [] as $result)<span class="ldot {{ $result->verdict->tone() }}"></span>@endforeach
								</span>
								<small>{{ $workflow['round'] ? __('ronde :number/:max', ['number' => $workflow['round']->number, 'max' => $maxRounds]) : __('nog niet gestart') }}</small>
							</span>
							<span class="chip {{ $workflow['round']?->status->tone() ?? 'gray' }}">{{ $workflow['round']?->status->label() ?? $workflow['step']->label() }}</span>
						</a>
					@endforeach
				</div>
			@else
				<div class="box">
					<div class="empty">
						<span class="bubble"><x-icon name="workflows"/></span>
						<b>{{ __('Nog niets opgeleverd') }}</b>
						<span>{{ __('Zodra een taak zijn werk aan de lenzen geeft verschijnt hij hier, met de stand van zijn rondes.') }}</span>
					</div>
				</div>
			@endif
		</section>

		<section class="section">
			<div class="section-head">
				<h2>{{ __('De lenzen') }}</h2>
				<label class="field-inline">
					<span class="k">{{ __('voor') }}</span>
					<select wire:model.live="lensProject" aria-label="{{ __('Project waarvoor je de lenzen instelt') }}">
						<option value="">{{ __('alle projecten') }}</option>
						@foreach ($lensProjects as $name)
							<option value="{{ $name }}">{{ $name }}</option>
						@endforeach
					</select>
				</label>
			</div>
			<div class="box list">
				@foreach ($lenses as $lens)
					<div class="lrow{{ $lens->isEnabled ? '' : ' off' }}" wire:key="lens-{{ $lens->lensId->id }}">
						<button class="toggle" type="button" aria-pressed="{{ $lens->isEnabled ? 'true' : 'false' }}"
							aria-label="{{ __('Zet :lens aan of uit', ['lens' => $lens->name]) }}"
							wire:click="toggleLens({{ $lens->lensId->id }}, {{ $lens->isEnabled ? 'false' : 'true' }})"
							@disabled($lensProject === '')></button>
						<span class="lname">{{ $lens->name }}</span>
						<span class="ldesc">{{ $lens->description }}</span>
						<span class="lpaths">
							@if ($lens->command)
								<span class="path">{{ $lens->command }}</span>
							@else
								@foreach ($lens->paths as $path)<span class="path">{{ $path }}</span>@endforeach
							@endif
						</span>
					</div>
				@endforeach
			</div>
			@if ($lensProject === '')
				<span class="side-note">{{ __('Kies hierboven een project om een lens aan of uit te zetten. Een lens staat aan tot een project zegt van niet.') }}</span>
			@endif
		</section>

		<section class="section">
			<div class="section-head">
				<h2>{{ __('Werkwijze voor de agent') }}</h2>
				<span class="side-note">{{ __('belandt als :path in elke werkkopie, buiten git om', ['path' => $contextPath]) }}</span>
			</div>

			<div class="box" style="padding:18px;display:flex;flex-direction:column;gap:16px">
				<div class="field">
					<label class="eyebrow" for="context-standing">{{ __('Altijd') }}</label>
					<textarea id="context-standing" class="textarea" style="min-height:150px" wire:model="context.standing"></textarea>
					<span class="muted" style="font-size:12.5px">{{ __('Wie hij is, dat er meer sessies lopen, en wat er nooit zonder jouw ja gebeurt. De taak, de branch en het pad worden er per werkkopie bij gezet.') }}</span>
				</div>

				@foreach ($steps as $case)
					<div class="field">
						<label class="eyebrow" for="context-{{ $case->value }}">{{ $case->title() }}</label>
						<textarea id="context-{{ $case->value }}" class="textarea" style="min-height:80px" wire:model="context.{{ $case->value }}"></textarea>
					</div>
				@endforeach

				<div class="sendrow">
					<x-busy-button action="saveContext" class="btn primary" icon="check" :busy="__('Opslaan...')">{{ __('Opslaan') }}</x-busy-button>
					@if ($contextSaved)<span class="hint">{{ $contextSaved }}</span>@endif
				</div>
			</div>
		</section>

		<section class="section">
			<div class="section-head">
				<h2>{{ __('Gelden voor elke ronde') }}</h2>
			</div>
			<dl class="rules box">
				<div><dt>{{ __('Wanneer') }}</dt><dd>{{ __('Een ronde start zodra een oplevering iets oplevert, niet bij elke vraag aan de agent.') }}</dd></div>
				<div><dt>{{ __('Wie doet mee') }}</dt><dd>{{ __('Een lens doet mee als minstens één gewijzigd bestand op zijn paden past.') }}</dd></div>
				<div><dt>{{ __('Wat een lens krijgt') }}</dt><dd>{{ __('De gewijzigde bestanden op zijn paden, plus CLAUDE.md en CLAUDE.local.md die hij zelf leest. Hij begint zonder de sessie van de agent die het bouwde.') }}</dd></div>
				<div><dt>{{ __('Wat een lens teruggeeft') }}</dt><dd>{{ __('Eén blok, met het verdict op de eerste regel en daaronder de bevindingen met bestand en regel.') }}</dd></div>
				<div><dt>{{ __('Drempel') }}</dt><dd>{{ __('Na :max rondes zonder schone uitkomst stopt het en krijg jij het rapport.', ['max' => $maxRounds]) }}</dd></div>
			</dl>
		</section>
	</div>
</div>
