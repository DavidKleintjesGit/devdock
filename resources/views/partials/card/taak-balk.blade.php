@if ($workingCopyTask?->isRunning)
	<div class="notice running" wire:poll.3s="followWorkingCopyTask">
		<span class="notice-icon"><span class="sdot busy"></span></span>
		<span class="notice-text"><b>{{ $workingCopyTask->task->busyLabel() }}</b><span>{{ __('Dit loopt in de achtergrond; je kunt intussen gewoon verder.') }}</span></span>
	</div>
@elseif ($workingCopyTask?->failure && ! $isSetup)
	<div class="notice failed">
		<span class="notice-icon"><x-icon name="alert" small/></span>
		<span class="notice-text"><b>{{ $workingCopyTask->task->failedLabel() }}</b><span>{{ $workingCopyTask->failure }}</span></span>
		<button class="icon-btn" type="button" wire:click="dismissWorkingCopyTask" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button>
	</div>
@endif
