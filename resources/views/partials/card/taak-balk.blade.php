@if ($workingCopyTask?->isRunning)
	<div class="banner amber" style="padding:12px 24px;display:flex;align-items:center;gap:12px" wire:poll.3s>
		<span class="sdot busy" style="box-shadow:none"></span>
		<span class="grow" style="display:flex;flex-direction:column;gap:2px"><b>{{ $workingCopyTask->task->busyLabel() }}</b><span>{{ __('Dit loopt in de achtergrond; je kunt intussen gewoon verder.') }}</span></span>
	</div>
@elseif ($workingCopyTask?->failure)
	<div class="alert red dfail">
		<x-icon name="alert" small/><span class="grow"><b>{{ $workingCopyTask->task->failedLabel() }}</b><br>{{ $workingCopyTask->failure }}</span>
		<button class="icon-btn" type="button" wire:click="dismissWorkingCopyTask" aria-label="{{ __('Melding weg') }}" style="width:26px;height:26px"><x-icon name="x" small/></button>
	</div>
@endif
