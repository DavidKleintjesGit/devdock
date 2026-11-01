<section class="dsec">
	<div class="field">
		<label for="new-branch">{{ __('Branch') }}</label>
		<div style="display:flex;gap:8px">
			<input id="new-branch" class="input mono" type="text" wire:model="newBranch" placeholder="{{ $card->branch ?? 'feature/' . ($card->ticketKey ?? 'naam') }}" autocomplete="off" @disabled($card->branch !== null)>
			@if ($card->branch === null && $card->ticketKey === null)
				<x-busy-button action="suggestBranchName" class="btn sm" icon="pencil" :busy="__('Bezig...')">{{ __('Laat de agent het bedenken') }}</x-busy-button>
			@endif
		</div>
		<span class="hint">{{ $card->branch ? __('De werkkopie komt op de branch van deze taak.') : __('Een naam die nog niet bestaat wordt van de hoofdlijn afgesneden, zonder hem te volgen.') }}</span>
	</div>

	<div class="field">
		<label for="new-database">{{ __('Database') }}</label>
		<select id="new-database" class="input" wire:model.live="newDatabaseMode">
			<option value="own">{{ __('Eigen database aanmaken') }}</option>
			<option value="shared">{{ __('Database van het project delen') }}</option>
			<option value="empty">{{ __('Leeg, zelf inrichten') }}</option>
		</select>
		@if ($databaseSuggestion)
			<span class="hint">
				@if ($newDatabaseMode === 'shared')
					{{ __('Een migratie in de kopie verandert dan ook de database van het project.') }}
				@else
					{{ __('Wordt') }} <span class="mono">{{ $databaseSuggestion }}</span>{{ $newDatabaseMode === 'own' ? __(', als kopie van de projectdatabase.') : __(', leeg.') }} {{ __('De naam pas je later aan op Deze kopie.') }}
				@endif
			</span>
		@endif
	</div>
</section>
