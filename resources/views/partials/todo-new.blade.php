<div class="dialog-wrap" wire:click.self="cancelTodo">
	<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="todo-title">
		<div class="dtop">
			<h2 id="todo-title">{{ __('To-do toevoegen') }}</h2>
			<button class="icon-btn" type="button" wire:click="cancelTodo" aria-label="{{ __('Sluiten') }}"><x-icon name="x"/></button>
		</div>

		<div class="dbody">
			<p>{{ __('Een kaart zonder ticket. Hij komt in Te doen, en richt je later in zoals elke andere kaart.') }}</p>

			<div class="field">
				<label for="todo-text">{{ __('Wat moet er gebeuren') }}</label>
				<input id="todo-text" @class(['input', 'invalid' => $errors->has('todoTitle')]) type="text" wire:model="todoTitle" wire:keydown.enter="createTodo" autocomplete="off" autofocus>
				@error('todoTitle')<span class="err">{{ $message }}</span>@enderror
			</div>

			<div class="field">
				<label for="todo-project">{{ __('Project') }}</label>
				<select id="todo-project" class="input" wire:model="todoProject">
					<option value="">{{ __('Geen project') }}</option>
					@foreach ($allProjectNames as $name)
						<option value="{{ $name }}">{{ $name }}</option>
					@endforeach
				</select>
			</div>

			<div class="field">
				<label for="todo-note">{{ __('Notitie') }}</label>
				<textarea id="todo-note" class="textarea" rows="3" wire:model="todoNote"></textarea>
			</div>

			<div class="switchrow">
				<span class="txt"><b>{{ __('Voor later') }}</b><small>{{ __('Hij komt in de backlog in plaats van in Te doen.') }}</small></span>
				<button class="switch" type="button" role="switch" aria-checked="{{ $todoLater ? 'true' : 'false' }}" wire:click="$toggle('todoLater')" aria-label="{{ __('Voor later') }}"></button>
			</div>
		</div>

		<div class="dfoot">
			<span class="hint"></span>
			<button class="btn text" type="button" wire:click="cancelTodo">{{ __('Annuleren') }}</button>
			<button class="btn primary" type="button" wire:click="createTodo"><x-icon name="plus" small/>{{ __('Toevoegen') }}</button>
		</div>
	</div>
</div>
