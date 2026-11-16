@if ($workingCopyTask?->isRunning)
	<div class="notice running" wire:poll.2s="followWorkingCopyTask">
		<span class="notice-icon"><span class="sdot busy"></span></span>
		<span class="notice-text"><b>{{ $workingCopyTask->task->busyLabel() }}</b><span>{{ $workingCopyTask->queued === [] ? __('Dit loopt in de achtergrond; je kunt intussen gewoon verder of het paneel sluiten.') : trans_choice('Daarna nog :count klus in de rij. Je kunt intussen gewoon verder of het paneel sluiten.|Daarna nog :count klussen in de rij. Je kunt intussen gewoon verder of het paneel sluiten.', count($workingCopyTask->queued), ['count' => count($workingCopyTask->queued)]) }}</span></span>
	</div>
@elseif ($workingCopyTask?->failure && $workingCopyTask->task === \Src\Worktree\Enums\WorkingCopyTaskEnum::LINK && ! $isSetup)
	<div class="notice failed">
		<span class="notice-icon"><x-icon name="alert" small/></span>
		<span class="notice-text"><b>{{ $workingCopyTask->task->failedLabel() }}</b><span>{{ $workingCopyTask->failure }}</span></span>
		<button class="icon-btn" type="button" wire:click="dismissWorkingCopyTask" aria-label="{{ __('Melding weg') }}"><x-icon name="x" small/></button>
	</div>
@endif
