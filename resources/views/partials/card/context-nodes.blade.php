@foreach ($nodes as $node)
	@if ($node['type'] === 'folder')
		<details class="context-node-folder" open>
			<summary><x-icon name="chev" small/><x-icon name="folder" small/><span class="name">{{ $node['name'] }}</span><span class="count">{{ $node['count'] }}</span></summary>
			<div class="context-node-children">
				@include('partials.card.context-nodes', ['nodes' => $node['children']])
			</div>
		</details>
	@else
		<button type="button" @class(['context-file', 'on' => $contextOpen?->key === $node['file']->key])
			@if ($node['file']->tab) wire:click="setDrawerTab('{{ $node['file']->tab }}')" @else wire:click="openContextFile('{{ $node['file']->key }}')" @endif
			@if ($contextOpen?->key === $node['file']->key) aria-current="true" @endif>
			<x-icon name="file" small/>
			<span class="grow">
				<span class="name">{{ $node['name'] }}</span>
				@if ($node['file']->description)<span class="hint">{{ $node['file']->description }}</span>@endif
			</span>
			<span @class(['audience', $node['audience']->value]) title="{{ __('Krijgen') }}: {{ $node['audience']->label() }}">{{ $node['audience']->shortLabel() }}</span>
		</button>
	@endif
@endforeach
