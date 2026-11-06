<div class="dbody tab-skeleton" aria-busy="true" aria-label="{{ __('Laden') }}">
	{{-- Een tab die subtabs heeft, laat bij het wisselen eerst zijn subtabbalk zien. --}}
	<template x-if="['Agent', 'Review'].includes(pendingView)">
		<div class="subbar"><span class="segmented bone-segmented"><span class="bone line"></span><span class="bone line"></span></span></div>
	</template>
	<template x-if="pendingView === 'Werkkopie'">
		<div class="subbar"><span class="segmented bone-segmented"><span class="bone line"></span><span class="bone line"></span><span class="bone line"></span><span class="bone line"></span></span></div>
	</template>

	<template x-if="pendingView === 'Overzicht'">
		<div class="skeleton-workspace">
			<div class="panel-grid ticket-grid">
				<div class="bone-panel">
					<div class="bone-head"><span class="bone line" style="width:38%"></span><span class="bone line" style="width:96px"></span></div>
					<div class="bone-body">
						<span class="bone line" style="width:94%"></span>
						<span class="bone line" style="width:88%"></span>
						<span class="bone line" style="width:91%"></span>
						<span class="bone line" style="width:60%"></span>
						<span class="bone line" style="width:84%;margin-top:10px"></span>
						<span class="bone line" style="width:48%"></span>
					</div>
				</div>
				<div class="bone-panel">
					<div class="bone-head"><span class="bone line" style="width:56%"></span></div>
					@foreach ([1, 2, 3] as $row)
						<div class="bone-row-block"><span class="bone line" style="width:40%"></span><span class="bone line" style="width:86%"></span></div>
					@endforeach
				</div>
			</div>
		</div>
	</template>

	<template x-if="['Agent', 'Agent:gesprek'].includes(pendingView)">
		<div class="skeleton-chat">
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
			<div class="composer bone-composer">
				<div class="composer-box">
					<span class="bone line" style="width:46%;margin:16px 14px 10px"></span>
					<div class="composer-tools"><span class="bone dot"></span><span class="bone line" style="width:180px;height:26px;border-radius:9px"></span><span class="grow"></span><span class="bone line" style="width:80px;height:26px;border-radius:9px"></span><span class="bone line" style="width:38px;height:38px;border-radius:10px"></span></div>
				</div>
			</div>
		</div>
	</template>

	<template x-if="['Agent:context', 'Review:lenzen'].includes(pendingView)">
		<div class="skeleton-workspace fill">
			<div class="bone-panel fill">
				<div class="bone-head"><span class="bone line" style="width:90px"></span><span class="bone line" style="width:40%"></span><span class="grow"></span><span class="bone line" style="width:160px;height:30px;border-radius:9px"></span></div>
				<div class="bone-columns">
					<div class="bone-tree">
						@foreach ([1, 2, 3] as $folder)
							<span class="bone line" style="width:42%"></span>
							<span class="bone-file"><span class="bone dot"></span><span class="bone line" style="width:66%"></span></span>
							<span class="bone-file"><span class="bone dot"></span><span class="bone line" style="width:52%"></span></span>
						@endforeach
					</div>
					<div class="bone-body">
						<span class="bone line" style="width:30%;height:14px"></span>
						<span class="bone line" style="width:62%"></span>
						<span class="bone line" style="width:94%;margin-top:14px"></span>
						<span class="bone line" style="width:88%"></span>
						<span class="bone line" style="width:76%"></span>
						<span class="bone line" style="width:90%"></span>
					</div>
				</div>
			</div>
		</div>
	</template>

	<template x-if="['Werkkopie', 'Werkkopie:wijzigingen'].includes(pendingView)">
		<div class="skeleton-workspace">
			<div class="panel-grid changes-grid">
				<div class="bone-panel">
					<div class="bone-head"><span class="bone line" style="width:90px"></span><span class="grow"></span><span class="bone line" style="width:220px;height:28px;border-radius:9px"></span></div>
					<div class="bone-body"><span class="bone line" style="width:70%"></span></div>
					@foreach ([1, 2, 3, 4] as $row)
						<span class="bone-row"><span class="bone dot"></span><span class="bone line grow"></span><span class="bone line" style="width:48px"></span></span>
					@endforeach
				</div>
				<div class="bone-panel">
					<div class="bone-head"><span class="bone line" style="width:70px"></span></div>
					<div class="bone-body"><span class="bone line" style="width:30%"></span><span class="bone block" style="height:40px"></span><span class="bone line" style="width:30%"></span><span class="bone block" style="height:110px"></span></div>
				</div>
			</div>
		</div>
	</template>

	<template x-if="pendingView === 'Werkkopie:branch'">
		<div class="skeleton-workspace">
			<div class="bone-panel">
				<div class="bone-head"><span class="bone line" style="width:150px"></span></div>
				<div class="bone-body"><span class="bone line" style="width:36%"></span></div>
				@foreach ([1, 2, 3] as $row)
					<span class="bone-row"><span class="bone dot"></span><span class="grow bone-stack"><span class="bone line" style="width:44%"></span><span class="bone line" style="width:70%;height:8px"></span></span><span class="bone line" style="width:64px;height:30px;border-radius:9px"></span></span>
				@endforeach
			</div>
		</div>
	</template>

	<template x-if="pendingView === 'Werkkopie:inrichting'">
		<div class="skeleton-workspace">
			<div class="bone-progress"><span class="bone line" style="width:40%"></span><span class="grow"></span><span class="bone line" style="width:120px;height:30px;border-radius:9px"></span></div>
			<div class="panel-grid setup-grid">
				@foreach ([[1, 2, 3, 4], [1, 2, 3], [1, 2, 3, 4], [1, 2, 3]] as $rows)
					<div class="bone-panel">
						<div class="bone-head"><span class="bone line" style="width:46%"></span></div>
						@foreach ($rows as $row)
							<span class="bone-row"><span class="bone dot"></span><span class="grow bone-stack"><span class="bone line" style="width:50%"></span><span class="bone line" style="width:76%;height:8px"></span></span></span>
						@endforeach
					</div>
				@endforeach
			</div>
		</div>
	</template>

	<template x-if="pendingView === 'Werkkopie:kopie'">
		<div class="skeleton-workspace">
			<div class="fact-grid">
				@foreach ([1, 2, 3, 4, 5, 6] as $fact)
					<div class="bone-panel"><div class="bone-body"><span class="bone line" style="width:28%;height:8px"></span><span class="bone line" style="width:78%"></span><span class="bone line" style="width:54%;height:8px"></span></div></div>
				@endforeach
			</div>
		</div>
	</template>

	<template x-if="['Review', 'Review:rondes'].includes(pendingView)">
		<div class="skeleton-workspace">
			<div class="bone-progress"><span class="bone dot"></span><span class="bone line" style="width:44%"></span><span class="grow"></span><span class="bone line" style="width:150px"></span></div>
			<div class="bone-panel">
				<div class="bone-head"><span class="bone line" style="width:80px"></span><span class="bone line" style="width:90px"></span><span class="grow"></span><span class="bone line" style="width:110px"></span></div>
				@foreach ([1, 2, 3, 4] as $row)
					<span class="bone-row"><span class="bone dot"></span><span class="bone line" style="width:30%"></span><span class="bone line" style="width:20%"></span></span>
				@endforeach
			</div>
			@foreach ([1, 2] as $round)
				<div class="bone-panel"><div class="bone-head"><span class="bone line" style="width:80px"></span><span class="bone line" style="width:90px"></span><span class="grow"></span><span class="bone line" style="width:110px"></span></div></div>
			@endforeach
		</div>
	</template>

	<template x-if="pendingView === 'Koppelingen'">
		<div class="skeleton-workspace">
			<div class="link-grid">
				@foreach ([1, 2, 3, 4, 5, 6, 7] as $link)
					<div class="bone-panel bone-link"><span class="bone tile"></span><span class="grow bone-stack"><span class="bone line" style="width:44%"></span><span class="bone line" style="width:84%;height:8px"></span><span class="bone line" style="width:58%;height:8px"></span></span><span class="bone line" style="width:56px;height:26px;border-radius:8px"></span></div>
				@endforeach
			</div>
		</div>
	</template>
</div>
