<div class="dbody workspace">
	<div @class(['panel-grid', 'ticket-grid' => $reviewComments->isNotEmpty() || $changesRequestedBy])>
		<x-panel :title="$card->ticketKey ? __('Wat er moet gebeuren') : __('De notitie')">
			@if ($card->ticketUrl)
				<x-slot:actions><a class="btn xs" href="{{ $card->ticketUrl }}" target="_blank" rel="noopener"><x-icon name="external" small/>{{ __('Openen in Jira') }}</a></x-slot:actions>
			@endif
			@if ($description)
				<p class="ticket-text">{{ $description }}</p>
			@else
				<p class="muted">{{ __('Nog geen beschrijving. Een ticket of een notitie op deze taak vult dit.') }}</p>
			@endif
		</x-panel>

		@if ($reviewComments->isNotEmpty() || $changesRequestedBy)
			<x-panel :title="__('Feedback uit Bitbucket')" :meta="trans_choice(':count open|:count open', $reviewComments->count(), ['count' => $reviewComments->count()])" :padded="false">
				<div class="list flush">
					@if ($changesRequestedBy)
						<a class="row tall" href="{{ $activePullRequest?->url }}" target="_blank" rel="noopener" style="background:var(--amber-wash);flex-direction:column;align-items:flex-start;gap:3px">
							<span style="display:flex;gap:8px;align-items:center;font-size:12.5px"><b>{{ $changesRequestedBy }}</b><span class="label amber">{{ __('Requested changes') }}</span></span>
							<span class="muted">{{ __('Vraagt om wijzigingen op :pr.', ['pr' => $card->activePullRequest]) }}</span>
						</a>
					@endif
					@foreach ($reviewComments as $comment)
						<a class="row tall" href="{{ $comment->url }}" target="_blank" rel="noopener" style="flex-direction:column;align-items:flex-start;gap:3px">
							<span style="display:flex;gap:8px;font-size:12.5px;color:var(--muted)"><b style="color:var(--fg)">{{ $comment->author }}</b><span class="mono" style="font-size:12px">{{ $comment->path ? $comment->path . ($comment->line ? ':' . $comment->line : '') : __('op de pull request zelf') }}</span></span>
							<span style="color:var(--fg-2)">{{ $comment->title() }}</span>
						</a>
					@endforeach
				</div>
			</x-panel>
		@endif
	</div>
</div>
