<section class="section">
	<div class="section-head">
		<h2>{{ __('Toestemmingen') }}</h2>
		<span class="side-note">{{ trans_choice(':count regel|:count regels', $permissionTotal, ['count' => $permissionTotal]) }}</span>
	</div>

	@if ($ignoredProjects)
		<span class="note amber">
			<x-icon name="alert" small/>
			<span>{{ __('Deze projecten hebben eigen regels die Claude Code negeert, omdat de map niet vertrouwd is: :projects. Open daar één keer Claude Code en accepteer de vraag.', ['projects' => implode(', ', $ignoredProjects)]) }}</span>
		</span>
	@endif

	@foreach ($permissionGroups as $group)
		<x-fold :title="$group['title']" :summary="$group['summary']" :open="$group['open']">
			<span class="lpaths">
				@foreach ($group['rules'] as $rule)<span class="path {{ $rule->tone() }}" title="{{ $rule->label() }}">{{ $rule->pattern }}</span>@endforeach
			</span>
		</x-fold>
	@endforeach

	<span class="legend">
		<span>{{ __('Grijs mag, rood nooit, oranje vraagt eerst.') }}</span>
		<span>{{ __('Ze stapelen: eerst die van jou, dan die van het project. Een weigering wint altijd, uit welk bestand hij ook komt.') }}</span>
	</span>
</section>
