<div wire:ignore x-data>
	<template x-if="$store.board.pending && $store.board.pending.card !== $store.board.openCard">
		<div>
			<div class="scrim" @click="$store.board.pending = null"></div>
			<aside class="drawer" aria-busy="true" aria-label="{{ __('Laden') }}">
				<div class="dbar">
					<span class="kind">{{ __('Kaart') }} · <span x-text="$store.board.pending.key"></span></span>
					<button class="icon-btn" type="button" @click="$store.board.pending = null" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
				</div>
				<div class="dhead">
					<h2 x-text="$store.board.pending.title"></h2>
				</div>
				<div class="skeleton steps-skeleton">
					@foreach ([1, 2, 3, 4] as $step)
						<span class="bone line"></span>
					@endforeach
				</div>
				<nav class="tabs" aria-hidden="true">
					@foreach (['Overzicht', 'Agent', 'Werkkopie', 'Review', 'Koppelingen'] as $name)
						<button type="button" tabindex="-1" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ __($name) }}</button>
					@endforeach
				</nav>
				<div class="dbody">
					@include('partials.skeletons.lines')
				</div>
			</aside>
		</div>
	</template>
</div>
