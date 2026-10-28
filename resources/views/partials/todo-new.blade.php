<div class="modal-wrap" wire:click.self="cancelTodo">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="todo-title">
		<div class="mhead">
			<h2 id="todo-title">{{ __('To-do toevoegen') }}</h2>
			<button class="btn icon ghost close" type="button" wire:click="cancelTodo" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>

		<div class="mbody">
			<div class="field">
				<label class="eyebrow" for="todo-text">{{ __('Wat moet er gebeuren') }}</label>
				<input id="todo-text" class="input" type="text" wire:model="todoTitle" wire:keydown.enter="createTodo" autocomplete="off">
				@error('todoTitle')<span class="note red"><x-icon name="alert" small/><span>{{ $message }}</span></span>@enderror
			</div>

			<div class="field">
				<label class="eyebrow" for="todo-project">{{ __('Project') }}</label>
				<select id="todo-project" class="input" wire:model="todoProject">
					<option value="">{{ __('Geen project') }}</option>
					@foreach ($allProjectNames as $name)
						<option value="{{ $name }}">{{ $name }}</option>
					@endforeach
				</select>
			</div>

			<div class="field">
				<label class="eyebrow" for="todo-note">{{ __('Notitie') }}</label>
				<textarea id="todo-note" class="input" rows="3" wire:model="todoNote"></textarea>
			</div>
		</div>

		<div class="mfoot">
			<button class="btn" type="button" wire:click="cancelTodo">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="createTodo"><x-icon name="plus" small/>{{ __('Toevoegen') }}</button>
		</div>
	</div>
</div>
