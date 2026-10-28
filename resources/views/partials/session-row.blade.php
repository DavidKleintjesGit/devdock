<div class="srow scols{{ in_array($row->uuid, $selected, true) ? ' sel' : '' }}{{ $openSession === $row->uuid ? ' open' : '' }}" wire:key="row-{{ $row->uuid }}">
	<label class="checkcell">
		<input class="check" type="checkbox" wire:click="toggleSelected('{{ $row->uuid }}')" @checked(in_array($row->uuid, $selected, true))>
		<span class="sr">{{ __('Selecteer :name', ['name' => $row->name()]) }}</span>
	</label>
	<button class="rowlink sname" type="button" wire:click="open('{{ $row->uuid }}')">
		<b class="{{ $row->title ? '' : 'anon' }}">{{ $row->name() }}</b>
		<span class="sub2">
			<span class="mono">{{ $row->uuid }}</span>
			@if ($row->mismatchedTicket())<span class="chip red">{{ __('Naam ≠ :ticket', ['ticket' => $row->mismatchedTicket()]) }}</span>@endif
		</span>
		<span class="m-meta">
			<span class="chip {{ $row->status->tone() }}">{{ $row->status->label() }}</span>
			<span>{{ $row->project ?? __("Zonder project") }}</span>
			<span>{{ __(':count prompts', ['count' => $row->promptCount]) }}</span>
			<span class="{{ $row->isLarge ? 'big' : '' }}">{{ $row->sizeLabel() }}</span>
			<span>{{ $row->lastActiveLabel() }}</span>
		</span>
	</button>
	<span class="c-status"><span class="chip {{ $row->status->tone() }}">{{ $row->status->label() }}</span></span>
	<span class="cell mono c-proj">{{ $row->project ?? __("Zonder project") }}</span>
	<span class="cell mono c-branch">{{ $row->branch ?: __('geen branch') }}</span>
	<span class="num mono">{{ $row->promptCount }}</span>
	<span class="num mono{{ $row->isLarge ? ' big' : '' }}">{{ $row->sizeLabel() }}</span>
	<span class="num c-last">{{ $row->lastActiveLabel() }}</span>
	<span class="actions">
		@if ($row->mismatchedTicket())
			<button class="btn sm above" type="button" wire:click="rename('{{ $row->uuid }}')">{{ __('Hernoemen') }}</button>
		@endif
	</span>
</div>
