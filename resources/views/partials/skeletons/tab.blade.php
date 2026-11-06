<div class="dbody tab-skeleton" aria-busy="true" aria-label="{{ __('Laden') }}">
	<template x-if="pendingTab === 'Agent' && ! pendingSubtab">
		<div class="skeleton chat-skeleton">
			<span class="bone bubble" style="width:58%"></span>
			<span class="bone line" style="width:24%;height:8px"></span>
			<span class="bone line" style="width:94%"></span>
			<span class="bone line" style="width:86%"></span>
			<span class="bone line" style="width:70%"></span>
			<span class="bone bubble" style="width:38%;height:30px"></span>
			<span class="bone line" style="width:90%"></span>
			<span class="bone line" style="width:62%"></span>
		</div>
	</template>

	<template x-if="pendingTab === 'Werkkopie' && ! pendingSubtab">
		<div class="skeleton">
			<span class="bone line" style="width:30%"></span>
			@foreach ([1, 2, 3] as $row)
				<span class="bone-row"><span class="bone dot"></span><span class="bone line grow"></span><span class="bone line" style="width:48px"></span></span>
			@endforeach
			<span class="bone line" style="width:26%;margin-top:14px"></span>
			@foreach ([1, 2] as $row)
				<span class="bone-row"><span class="bone line grow"></span><span class="bone line" style="width:36px"></span></span>
			@endforeach
		</div>
	</template>

	<template x-if="pendingSubtab || (pendingTab && pendingTab !== 'Agent' && pendingTab !== 'Werkkopie')">
		@include('partials.skeletons.lines')
	</template>
</div>
