<div>
	@if ($rows !== [])
		<div class="dialog-wrap" wire:click.self="close">
			<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="clean-up-title">
				<div class="dtop">
					<h2 id="clean-up-title">{{ trans_choice(':action · :count kaart|:action · :count kaarten', count($rows), ['action' => $cardAction->label(), 'count' => count($rows)]) }}</h2>
					<button class="icon-btn" type="button" wire:click="close" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
				</div>
				<div class="dbody">
					<p>{{ $cardAction->explanation() }}</p>
					<ul class="clean-up-cards">
						@foreach ($rows as $row)
							<li @class(['loses' => $row->losesWork()]) wire:key="clean-up-{{ $row->card }}">
								<span class="clean-up-head"><span class="mono">{{ $row->label }}</span><b>{{ $row->title }}</b></span>
								@if ($row->losesWork())
									<span class="clean-up-loss"><x-icon name="alert" small/>{{ __(':work. Dit gaat verloren.', ['work' => $row->workAtStake]) }}</span>
								@endif
								@if ($cardAction->deletesHistory() && ! $row->isTodo)
									<span class="hint">{{ __('Geen eigen to-do: deze kaart wordt gearchiveerd, het ticket of de pull request blijft.') }}</span>
								@endif
								@if ($row->goes !== [])
									<ul class="cleanup">@foreach ($row->goes as $line)<li>{{ $line }}</li>@endforeach</ul>
								@else
									<span class="hint">{{ __('Geen werkkopie: alleen de koppelingen van de taak gaan weg.') }}</span>
								@endif
							</li>
						@endforeach
					</ul>
					@if ($losesWork)
						<label class="tick clean-up-accept">
							<input type="checkbox" wire:model.live="acceptsLoss">
							<span class="mark"></span>
							<span>{{ __('Ik weet dat het werk hierboven verloren gaat') }}</span>
						</label>
					@endif
					@if ($failure)
						<p class="alert" style="margin:0">{{ $failure }}</p>
					@endif
				</div>
				<div class="dfoot">
					<span class="hint">{{ __('Jira, Bitbucket en origin blijven zoals ze zijn') }}</span>
					<button class="btn text" type="button" wire:click="close">{{ __('Annuleren') }}</button>
					<button @class(['btn', 'danger' => $cardAction->deletesHistory() || $losesWork, 'primary' => ! $cardAction->deletesHistory() && ! $losesWork]) type="button" wire:click="confirm" @disabled($losesWork && ! $acceptsLoss)>{{ $cardAction->label() }}</button>
				</div>
			</div>
		</div>
	@endif
</div>
