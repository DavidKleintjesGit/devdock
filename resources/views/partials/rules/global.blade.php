<header class="phead">
	<div class="inner">
		<div class="titles">
			<a class="back-link" href="{{ route('rules') }}"><x-icon name="back" small/>{{ __('Regels') }}</a>
			<span class="eyebrow">{{ __('Regels') }}</span>
			<h1>{{ __('Globaal') }}</h1>
			<span class="lede">{{ __('Alleen voor jou, in elk project. Staat in :folder, niet in een repo: je collega\'s krijgen dit niet.', ['folder' => '~/.claude']) }}</span>
		</div>
		<button class="btn primary" type="button" wire:click="startRule"><x-icon name="plus" small/>{{ __('Regel maken') }}</button>
	</div>
</header>

<div class="pbody">
	<div class="stack">
		<section class="section">
			<div class="section-head"><h2>{{ __('Regelbestanden') }}</h2><span class="note">{{ __('elke agent krijgt ze mee') }}</span></div>
			@if ($files === [])
				<div class="empty">
					<b>{{ __('Je hebt nog geen globale regels') }}</b>
					<span><span class="mono">~/.claude/CLAUDE.md</span> {{ __('bestaat niet. Alles wat je nu afspreekt, staat per project.') }}</span>
					<button class="btn sm" type="button" wire:click="startRule">{{ __('Regel maken') }}</button>
				</div>
			@else
				<div class="list">
					@foreach ($files as $path => $contents)
						<details class="row" style="display:block;padding:0" wire:key="global-{{ $path }}">
							<summary class="frow" style="list-style:none"><span class="fname"><span class="mono">~/.claude/{{ $path }}</span></span><span></span><span class="lines">{{ substr_count($contents, "\n") + 1 }}</span><x-icon name="chev" small/></summary>
							<pre class="code-block" style="margin:0 18px 16px;white-space:pre-wrap">{{ $contents }}</pre>
						</details>
					@endforeach
				</div>
			@endif
		</section>

		<section class="section">
			<div class="section-head"><h2>{{ __('Toestemmingen') }}</h2><span class="note"><span class="mono">~/.claude/settings.json</span> · {{ trans_choice(':count regel|:count regels', $allowed->count() + $denied->count() + $asked->count(), ['count' => $allowed->count() + $denied->count() + $asked->count()]) }}</span></div>
			<div class="card" style="padding:16px 18px;display:flex;flex-direction:column;gap:14px">
				<div style="display:flex;flex-direction:column;gap:8px" x-data="{ all: false }">
					<span class="hint">{{ __('Mag · :count', ['count' => $allowed->count()]) }}</span>
					<span class="chips">
						@foreach ($allowed as $permission)<span class="path" x-show="all || {{ $loop->index }} < 8">{{ $permission->pattern }}</span>@endforeach
						@if ($allowed->count() > 8)<button class="btn text xs" type="button" style="height:24px" x-show="! all" @click="all = true">{{ __('nog :count', ['count' => $allowed->count() - 8]) }}</button>@endif
					</span>
				</div>
				@if ($asked->isNotEmpty())
					<div style="display:flex;flex-direction:column;gap:8px">
						<span class="hint">{{ __('Vraagt eerst · :count', ['count' => $asked->count()]) }}</span>
						<span class="chips">@foreach ($asked as $permission)<span class="path ask">{{ $permission->pattern }}</span>@endforeach</span>
					</div>
				@endif
				<div style="display:flex;flex-direction:column;gap:8px">
					<span class="hint">{{ __('Nooit · :count', ['count' => $denied->count()]) }}</span>
					<span class="chips">@forelse ($denied as $permission)<span class="path deny">{{ $permission->pattern }}</span>@empty<span class="hint">{{ __('geen') }}</span>@endforelse</span>
				</div>
				<div style="display:flex;align-items:center;gap:10px;padding-top:12px;border-top:1px solid var(--line);font-size:13px"><span class="muted grow">{{ __('Standaardmodus') }}</span><span class="mono">{{ $defaultMode ?? __('niet gezet') }}</span></div>
			</div>
			<span class="hint">{{ __('Werkt door in alle :count projecten. Een project kan er eigen regels bovenop zetten; een weigering wint altijd.', ['count' => $projectCount]) }}</span>
		</section>
	</div>
</div>
