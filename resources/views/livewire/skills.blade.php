<div class="split{{ $page['kind'] === 'none' ? '' : ' has-page' }}">
	<nav class="index" aria-label="{{ __('Skills') }}">
		<label class="search" style="flex:none;max-width:none">
			<x-icon name="search" small/>
			<span class="sr">{{ __('Zoek een skill of project') }}</span>
			<input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Zoek een skill of project') }}" autocomplete="off">
		</label>

		<a class="block" href="#" wire:click.prevent="showSection('global')" @if ($section === 'global') aria-current="page" @endif style="flex-direction:row;align-items:center">
			<span class="grow" style="display:flex;flex-direction:column"><b>{{ __('Globaal') }}</b><small>{{ __('alleen jij, in elk project') }}</small></span>
			<span class="side-note">{{ $globalCount }}</span>
		</a>

		<div class="index-group">
			<div class="eyebrow">{{ __('Bibliotheken') }}</div>
			@foreach ($libraries as $libraryEntity)
				<a class="block" href="#" wire:click.prevent="showLibrary({{ $libraryEntity->id }})" wire:key="slib-{{ $libraryEntity->id }}" @if ($library === $libraryEntity->id) aria-current="page" @endif>
					<b>{{ $libraryEntity->name }}</b>
					<small>{{ $libraryEntity->skills() === [] ? __('nog leeg') : trans_choice(':count skill|:count skills', count($libraryEntity->skills()), ['count' => count($libraryEntity->skills())]) }}</small>
				</a>
			@endforeach
			<button class="item" type="button" style="color:var(--accent)" wire:click="startLibrary"><x-icon name="plus" small/>{{ __('Bibliotheek toevoegen') }}</button>
		</div>

		<a class="block" href="#" wire:click.prevent="showSection('plugins')" @if ($section === 'plugins') aria-current="page" @endif style="flex-direction:row;align-items:center">
			<span class="grow" style="display:flex;flex-direction:column"><b>{{ __('Plugins') }}</b><small>{{ __('alleen-lezen') }}</small></span>
			<span class="side-note">{{ $pluginCount }}</span>
		</a>

		@if ($favorites !== [])
			<div class="index-group">
				<div class="eyebrow">{{ __('Favorieten') }}</div>
				@foreach ($favorites as $favorite)
					<a href="#" wire:click.prevent="open('{{ $favorite['project'] }}|{{ $favorite['path'] }}')" wire:key="sfav-{{ $favorite['project'] }}-{{ $favorite['path'] }}">
						<x-icon name="star" small/>
						<span class="grow ellipsis mono" style="font-size:12.5px">{{ $favorite['path'] }}</span>
						<span class="side-note">{{ $favorite['project'] }}</span>
					</a>
				@endforeach
			</div>
		@endif

		<div class="index-group">
			<div class="eyebrow">
				<span>{{ __('Projecten · :count', ['count' => $withSkills + $withoutSkills]) }}</span>
				<button class="tchip" type="button" style="height:26px;border-radius:999px;letter-spacing:0;text-transform:none;font-size:12px" aria-pressed="{{ $onlyWithSkills ? 'true' : 'false' }}" wire:click="$toggle('onlyWithSkills')">{{ __('Met skills') }} <b>{{ $withSkills }}</b></button>
			</div>
			@foreach ($projectRows as $row)
				<a href="#" wire:click.prevent="showProject('{{ $row['name'] }}')" wire:key="sp-{{ $row['name'] }}" @if ($project === $row['name']) aria-current="page" @endif>
					<span class="grow ellipsis">{{ $row['name'] }}</span>
					<span class="side-note">{{ $row['count'] ?: '' }}</span>
				</a>
			@endforeach
			@if ($onlyWithSkills && $withoutSkills > 0)
				<button class="item more" type="button" wire:click="$set('onlyWithSkills', false)">{{ trans_choice(':count project zonder skills|:count projecten zonder skills', $withoutSkills, ['count' => $withoutSkills]) }}</button>
			@endif
		</div>
	</nav>

	<div class="page">
		@if ($libraryDone)
			<div class="alert green" style="margin:16px 44px 0;max-width:1000px"><x-icon name="check" small/><span class="grow">{{ $libraryDone }}</span><button class="icon-btn" type="button" style="width:26px;height:26px" wire:click="$set('libraryDone', null)" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button></div>
		@endif

		@switch ($page['kind'])
			@case ('project')
				@include('partials.skills.project', $page)
				@break
			@case ('global')
			@case ('plugins')
				@include('partials.skills.list', $page)
				@break
			@case ('library')
				@include('partials.skills.library', $page)
				@break
			@case ('skill')
				@include('partials.skills.skill', $page)
				@break
			@default
				<main class="main" style="padding:34px 44px">
					<div class="empty"><b>{{ __('Kies links een project') }}</b><span>{{ __('Je ziet dan welke skills een agent daar kan pakken.') }}</span></div>
				</main>
		@endswitch
	</div>

	@if ($skillDrawer)
		@include('partials.skill-drawer', $skillDrawer)
	@endif

	@if ($itemDrawer)
		@include('partials.library.item-drawer', $itemDrawer + ['kindLabel' => __('Bibliotheekskill')])
	@endif

	@if ($confirmingRollOut)
		@include('partials.library.roll-out')
	@endif

	@if ($addingLibrary)
		@include('partials.library.new', $newLibrary)
	@endif

	@if ($creating)
		@include('partials.skill-new', ['places' => $newSkillPlaces])
	@endif
</div>
