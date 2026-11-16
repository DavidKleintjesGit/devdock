{{-- The outline of each page, shown the moment you click to it while the page itself is on its way. --}}
<template id="skeleton-board">
	<div class="page fixed sk">
		<div class="sk-head"><span class="bone" style="width:90px;height:18px"></span><span class="bone" style="width:220px;height:32px;border-radius:9px"></span><span class="grow"></span><span class="bone" style="width:160px;height:32px;border-radius:9px"></span></div>
		<div class="sk-columns">
			@foreach ([5, 4, 3, 4] as $cards)
				<div class="sk-column">
					<span class="bone" style="width:40%;height:12px;margin-bottom:6px"></span>
					@for ($card = 0; $card < $cards; $card++)
						<span class="bone sk-card"></span>
					@endfor
				</div>
			@endforeach
		</div>
	</div>
</template>

<template id="skeleton-split">
	<div class="split has-page sk">
		<nav class="index sk-index">
			<span class="bone" style="height:34px;border-radius:9px;margin-bottom:10px"></span>
			@foreach ([70, 55, 80, 60, 75, 50, 65, 58] as $width)
				<span class="bone" style="width:{{ $width }}%;height:12px"></span>
			@endforeach
		</nav>
		<div class="page">
			<div class="sk-phead"><span class="bone" style="width:60px;height:10px"></span><span class="bone" style="width:260px;height:24px"></span><span class="bone" style="width:420px;height:12px"></span></div>
			<div class="sk-body">
				@foreach ([1, 2, 3, 4, 5, 6] as $row)
					<span class="bone sk-row"></span>
				@endforeach
			</div>
		</div>
	</div>
</template>

<template id="skeleton-page">
	<div class="page sk">
		<div class="sk-phead"><span class="bone" style="width:60px;height:10px"></span><span class="bone" style="width:260px;height:24px"></span><span class="bone" style="width:420px;height:12px"></span></div>
		<div class="sk-body">
			@foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $row)
				<span class="bone sk-row"></span>
			@endforeach
		</div>
	</div>
</template>
