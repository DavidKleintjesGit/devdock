<div class="dbody">
	@if ($failure)
		<div class="alert red dfail"><x-icon name="alert" small/><span class="grow">{{ $failure }}</span></div>
	@endif

	<section class="dsec">
		<div class="list">
			@foreach ($setupSteps as $setupStep)
				<div class="row setup-step" wire:key="setup-{{ $setupStep['key'] }}">
					@if ($setupStep['isDone'])
						<span class="tick" aria-hidden="true"><input type="checkbox" checked disabled><span class="mark"></span></span>
					@else
						<span class="step-number" aria-hidden="true">{{ $loop->iteration }}</span>
					@endif
					<span class="key">{{ $setupStep['label'] }}</span>

					@if ($setupStep['key'] === 'project')
						@if ($setupStep['isDone'])
							<span class="val mono">{{ $card->project }}</span>
						@else
							<span class="val setup-line">
								<select class="input" wire:model="linkProject">
									<option value="">{{ __('Kies een project') }}</option>
									@foreach ($projectChoices as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
								</select>
								<button class="btn sm" type="button" wire:click="link('project')">{{ __('Vastzetten') }}</button>
							</span>
						@endif
					@elseif ($setupStep['key'] === 'worktree')
						@if ($setupStep['isDone'])
							<span class="val setup-value">
								<span class="mono">{{ $worktree->branch }}</span>
								<span class="hint"><span class="mono">{{ $worktree->path }}</span> · {{ $worktree->origin->label() }} · {{ $worktree->ownDatabase ? __('eigen database') : __('database van het project') }}</span>
							</span>
						@elseif ($card->project === null)
							<span class="val hint">{{ __('Kies eerst een project') }}</span>
						@else
							<span class="val setup-fields">
								<label class="setup-field">
									<span class="hint">{{ __('Branch') }}</span>
									<select class="input mono" wire:model.live="setupBranch">
										@foreach ($workingCopyChoices as $choice)
											<option value="{{ $choice->name }}">{{ $choice->name }}{{ $choice->matchesTicket ? ' · ' . __('ticketnummer') : '' }}{{ $choice->place->tag() ? ' · ' . $choice->place->tag() : '' }}</option>
										@endforeach
										<option value="__new__">{{ __('Nieuwe branch…') }}</option>
									</select>
								</label>
								@if ($newBranchChosen)
									<label class="setup-field">
										<span class="hint">{{ __('Naam') }}</span>
										<input class="input mono" type="text" wire:model.live.debounce.400ms="newBranch" placeholder="{{ $card->ticketKey ? 'feature/' . $card->ticketKey : 'feature/naam' }}">
									</label>
									@if ($card->ticketKey === null)
										<x-busy-button action="suggestBranchName" class="btn text xs" icon="pencil" :busy="__('Bezig...')" style="align-self:flex-start">{{ __('Laat de agent het bedenken') }}</x-busy-button>
									@endif
								@endif
								@if ($preparePlan['isNew'])
									<label class="setup-field">
										<span class="hint">{{ __('Database') }}</span>
										<select class="input" wire:model.live="newDatabaseMode">
											@foreach ($databaseModes as $mode => $label)<option value="{{ $mode }}">{{ $label }}</option>@endforeach
										</select>
									</label>
								@endif
								<span class="setup-plan">
									<span @class(['plan-text', 'warn' => $preparePlan['isProject']])>
										<x-icon :name="$preparePlan['icon']" small/>
										<span>{{ $preparePlan['text'] }}@if ($preparePlan['isProject']) <button class="btn text xs" type="button" wire:click="keepOwnWorkingCopy">{{ __('Liever een eigen werkkopie') }}</button>@endif</span>
									</span>
									<x-busy-button action="prepareWorkingCopy" class="btn primary sm" :busy="__('Bezig...')">{{ __('Klaarzetten') }}</x-busy-button>
								</span>
								<span class="hint" wire:loading wire:target="prepareWorkingCopy">{{ $preparePlan['isNew'] ? __('De werkkopie wordt gemaakt en de database gekopieerd; dat kan een halve minuut duren.') : __('De werkkopie wordt gekoppeld.') }}</span>
							</span>
						@endif
					@else
						@if ($card->agent !== null)
							<span class="val setup-line">
								<span class="agent-badge">{{ mb_substr($card->agent->name, 0, 2) }}</span>
								<span class="grow"><b>{{ $card->agent->name }}</b><br><span class="hint">{{ __('Werkt in deze werkkopie') }}</span></span>
								<button class="btn primary sm" type="button" wire:click="setDrawerTab('Agent')">{{ __('Naar de agent') }}<x-icon name="arrow" small/></button>
							</span>
						@elseif ($worktree === null)
							<span class="val hint">{{ __('Komt zodra de werkkopie klaarstaat') }}</span>
						@else
							<span class="val setup-line">
								<span class="grow hint">{{ __('De agent blijft bij deze taak en werkt in deze werkkopie.') }}</span>
								<x-busy-button action="assignAgent" class="btn primary sm" :busy="__('Toewijzen...')">{{ __('Agent toewijzen') }}</x-busy-button>
							</span>
						@endif
					@endif
				</div>
			@endforeach
		</div>
	</section>

	<section class="dsec">
		<h3 class="eyebrow">{{ $card->ticketKey ? __('Het ticket') : __('De notitie') }}</h3>
		@if ($description)<p style="white-space:pre-line">{{ $description }}</p>@else<p class="muted">{{ __('Nog geen beschrijving.') }}</p>@endif
		@if ($card->ticketUrl)<a href="{{ $card->ticketUrl }}" target="_blank" rel="noopener" style="font-size:13px;text-decoration:none">{{ __('Openen in Jira') }}</a>@endif
	</section>
</div>

<div class="dfoot">
	<span class="hint">{{ $setupHint }}</span>
</div>
