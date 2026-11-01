<div class="split{{ $page['kind'] === 'none' ? '' : ' has-page' }}">
	<nav class="index" aria-label="{{ __('Regels') }}">
		<label class="search" style="flex:none;max-width:none">
			<x-icon name="search" small/>
			<span class="sr">{{ __('Zoek een regel of project') }}</span>
			<input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Zoek een regel of project') }}" autocomplete="off">
		</label>

		<a class="block" href="#" wire:click.prevent="showGlobal" @if ($global) aria-current="page" @endif>
			<b>{{ __('Globaal') }}</b>
			<small>{{ __('alleen jij, in elk project') }}</small>
		</a>

		<div class="index-group">
			<div class="eyebrow">{{ __('Bibliotheken') }}</div>
			@foreach ($libraries as $libraryEntity)
				<a class="block" href="#" wire:click.prevent="showLibrary({{ $libraryEntity->id }})" wire:key="lib-{{ $libraryEntity->id }}" @if ($library === $libraryEntity->id && $rule === '') aria-current="page" @endif>
					<b>{{ $libraryEntity->name }}</b>
					<small>{{ trans_choice(':count regel|:count regels', count($libraryEntity->rules()), ['count' => count($libraryEntity->rules())]) }} · {{ trans_choice(':count project|:count projecten', count($libraryEntity->followers), ['count' => count($libraryEntity->followers)]) }}</small>
				</a>
			@endforeach
			<button class="item" type="button" style="color:var(--accent)" wire:click="startLibrary"><x-icon name="plus" small/>{{ __('Bibliotheek toevoegen') }}</button>
		</div>

		@if ($favorites !== [])
			<div class="index-group">
				<div class="eyebrow">{{ __('Favorieten') }}</div>
				@foreach ($favorites as $favorite)
					<a href="#" wire:click.prevent="openFavorite('{{ $favorite['project'] }}', '{{ $favorite['path'] }}')" wire:key="fav-{{ $favorite['project'] }}-{{ $favorite['path'] }}">
						<x-icon name="star" small/>
						<span class="grow ellipsis mono" style="font-size:12.5px">{{ str_replace('.claude/rules/', '', $favorite['path']) }}</span>
						<span class="side-note">{{ $favorite['project'] }}</span>
					</a>
				@endforeach
			</div>
		@endif

		<div class="index-group">
			<div class="eyebrow">
				<span>{{ __('Projecten · :count', ['count' => $projectTotal]) }}</span>
				@if ($deviatingCount > 0 || $onlyDeviating)
					<button class="tchip" type="button" style="height:26px;border-radius:999px;letter-spacing:0;text-transform:none;font-size:12px" aria-pressed="{{ $onlyDeviating ? 'true' : 'false' }}" wire:click="$toggle('onlyDeviating')">{{ __('Wijkt af') }} <b style="color:var(--amber)">{{ $deviatingCount }}</b></button>
				@endif
			</div>
			@foreach ($projectRows as $row)
				<a href="#" wire:click.prevent="showProject('{{ $row['name'] }}')" wire:key="rp-{{ $row['name'] }}" @if ($project === $row['name']) aria-current="page" @endif>
					<span class="dot {{ $row['tone'] }}"></span>
					<span class="grow ellipsis">{{ $row['name'] }}</span>
					<span class="side-note {{ $row['noteTone'] }}">{{ $row['note'] }}</span>
				</a>
			@endforeach
			@if ($hiddenProjects > 0)
				<button class="item more" type="button" wire:click="$set('allProjects', true)">{{ __('Nog :count projecten', ['count' => $hiddenProjects]) }}</button>
			@endif
		</div>
	</nav>

	<div class="page">
		@if ($libraryDone)
			<div class="alert green" style="margin:16px 44px 0;max-width:1000px"><x-icon name="check" small/><span class="grow">{{ $libraryDone }}</span><button class="icon-btn" type="button" style="width:26px;height:26px" wire:click="$set('libraryDone', null)" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button></div>
		@endif

		@switch ($page['kind'])
			@case ('project')
				@include('partials.rules.project', $page)
				@break
			@case ('library')
				@include('partials.rules.library', $page)
				@break
			@case ('rule')
				@include('partials.rules.rule', $page)
				@break
			@case ('global')
				@include('partials.rules.global', $page)
				@break
			@default
				<main class="main" style="padding:34px 44px">
					<div class="empty">
						<b>{{ __('Kies links een project of bibliotheek') }}</b>
						<span>{{ __('Een project laat zien wat een agent daar leest; een bibliotheek wat je projecten zouden moeten hebben.') }}</span>
					</div>
				</main>
		@endswitch
	</div>

	@if ($fileDrawer)
		@include('partials.rules.file-drawer', $fileDrawer)
	@endif

	@if ($itemDrawer)
		@include('partials.library.item-drawer', $itemDrawer + ['kindLabel' => __('Bibliotheekregel')])
	@endif

	@if ($confirmingRollOut)
		@include('partials.library.roll-out')
	@endif

	@if ($addingLibrary)
		@include('partials.library.new', $newLibrary)
	@endif

	@if ($adding)
		@include('partials.rules.add', $adding)
	@endif

	@if ($landings)
		@include('partials.rules.landing', $landings)
	@endif

	@if ($newRule)
		@include('partials.rules.new', $newRule)
	@endif
</div>
