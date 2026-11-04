<div class="page">
	<header class="phead">
		<div class="inner">
			<div class="titles">
				<span class="eyebrow">{{ __('Toegang') }}</span>
				<div class="head-title">
					<h1>{{ __('Wie mag wat bereiken') }}</h1>
					<span class="label green">{{ __('git blijft bij jou') }}</span>
				</div>
				<p style="margin:0;max-width:68ch;color:var(--fg-2)">{{ __('Wat Claude Code mag als DevDock het voor een taak start. Elke start krijgt precies deze lijst mee, dus wat hier staat is wat geldt.') }}</p>
			</div>
		</div>
	</header>

	<main class="pbody">
		<div class="stack">
			<div class="alert teal">
				<x-icon name="lock" small/>
				<span class="grow"><b>{{ __('Alleen jij schrijft in git.') }}</b><br>{{ __('Committen, pushen en rebasen zijn de knoppen op de tab Werkkopie. Agents, lenzen en hulpvragen lezen git en verder niets, dubbel tegengehouden: een hook weigert het commando voordat het draait, en git zelf weigert elke wijziging aan een branch van het project en elke push, ook met --no-verify.') }}</span>
			</div>

			<section class="section">
				<div class="section-head">
					<h2 style="flex:none">{{ __('Per rol') }}</h2>
					<span class="note" style="flex:1">{{ __('als wie Claude Code gestart wordt') }}</span>
				</div>
				<div class="card" style="padding:0;overflow:auto">
					<table class="table">
						<thead>
							<tr>
								<th style="width:34%"></th>
								@foreach ($accessOverview->roleAccesses as $roleAccess)
									<th>{{ $roleAccess->agentRole->label() }}<br><span class="hint" style="font-weight:400">{{ $roleAccess->agentRole->explanation() }}</span></th>
								@endforeach
							</tr>
						</thead>
						<tbody>
							@foreach ($capabilities as $capability)
								<tr wire:key="capability-{{ $capability->value }}">
									<td><b>{{ $capability->label() }}</b>@if ($capability->hint())<br><span class="hint">{{ $capability->hint() }}</span>@endif</td>
									@foreach ($accessOverview->roleAccesses as $roleAccess)
										<td><span class="label {{ $capability->answerFor($roleAccess)->tone() }}">{{ $capability->answerFor($roleAccess)->label() }}</span></td>
									@endforeach
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</section>

			<section class="section">
				<div class="section-head">
					<h2 style="flex:none">{{ __('Git') }}</h2>
					<span class="note" style="flex:1">{{ __('de commando\'s die erdoor komen') }}</span>
				</div>
				<div class="list">
					<div class="kv">
						<span class="k">{{ __('Lezen') }}</span>
						<span class="v chips">@foreach ($accessOverview->gitReading as $command)<span class="path">{{ $command }}</span>@endforeach</span>
					</div>
					<div class="kv">
						<span class="k">{{ __('Alleen in hun lijstvorm') }}</span>
						<span class="v chips">@foreach ($accessOverview->gitListing as $command)<span class="path">{{ $command }}</span>@endforeach</span>
					</div>
					<div class="kv">
						<span class="k">{{ __('Al het andere wordt geweigerd, met deze reden aan de agent') }}</span>
						<span class="v"><span class="hint">“{{ $accessOverview->gitRefusal }}”</span></span>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="section-head">
					<h2 style="flex:none">{{ __('Atlassian en Docs') }}</h2>
					<span class="note" style="flex:1">{{ __('schrijvende tools geweigerd op elke server') }}</span>
				</div>
				<div class="list">
					<div class="kv">
						<span class="k">{{ __('Servers') }}</span>
						<span class="v chips">@foreach ($accessOverview->servers as $server)<span class="path">{{ $server }}</span>@endforeach</span>
					</div>
					<div class="kv">
						<span class="k">{{ __('Geweigerde tools') }}</span>
						<span class="v chips">@foreach ($accessOverview->writingTools as $tool)<span class="path deny">{{ $tool }}</span>@endforeach</span>
					</div>
					<div class="kv">
						<span class="k">{{ __('executeWrite voor de agent, achter de schrijfbewaking') }}</span>
						<span class="v chips">@forelse ($accessOverview->agentOperations as $operation)<span class="path" style="background:var(--accent-soft);color:var(--accent-ink)">{{ $operation }}</span>@empty<span class="hint">{{ __('niets') }}</span>@endforelse</span>
					</div>
				</div>
				<span class="hint">{{ __('Ingesteld in') }} <span class="mono">config/devdock.php</span> {{ __('onder') }} <span class="mono">access</span>. {{ __('Deze pagina laat het alleen zien.') }}</span>
			</section>
		</div>
	</main>
</div>
