@foreach ($nodes as $node)
	@if ($node->isFolder())
		<div class="tree-folder" x-data="{ open: true }" wire:key="tree-{{ $node->path }}">
			<button type="button" class="tree-row folder" style="--depth:{{ $depth }}" @click="open = ! open" :aria-expanded="open" title="{{ $node->path }}">
				<span class="tree-chev" :class="{ shut: ! open }"><x-icon name="down" small/></span>
				<span class="ellipsis">{{ $node->name }}</span>
				<span class="tree-count" x-show="! open">{{ $node->fileCount() }}</span>
			</button>
			<div x-show="open">
				@include('partials.diff-tree', ['nodes' => $node->children, 'depth' => $depth + 1])
			</div>
		</div>
	@else
		<button type="button" class="tree-row file" style="--depth:{{ $depth }}" aria-current="{{ $openDiffFile === $node->path ? 'true' : 'false' }}" x-on:click="document.getElementById(@js($node->change->anchor()))?.scrollIntoView({ block: 'start', behavior: 'smooth' })" wire:key="tree-{{ $node->path }}" title="{{ $node->path }} · {{ $node->change->kind->label() }}">
			<span class="tree-kind {{ $node->change->kind->tone() }}"><x-icon :name="$node->change->kind->icon()" small/></span>
			<span class="ellipsis grow">{{ $node->name }}</span>
			@if ($node->change->added)<span class="tree-plus">+{{ $node->change->added }}</span>@endif
			@if ($node->change->removed)<span class="tree-minus">−{{ $node->change->removed }}</span>@endif
		</button>
	@endif
@endforeach
