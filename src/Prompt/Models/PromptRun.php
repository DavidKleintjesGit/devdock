<?php

declare(strict_types=1);

namespace Src\Prompt\Models;

use Illuminate\Database\Eloquent\Model;

class PromptRun extends Model
{
	protected $fillable = [
		'card',
		'review_round_id',
		'project',
		'branch',
		'directory',
		'ticket_key',
		'session_uuid',
		'claims_session',
		'prompt',
		'status',
		'output',
		'message',
		'cost',
		'started_at',
		'finished_at',
	];

	protected function casts(): array
	{
		return [
			'claims_session' => 'boolean',
			'cost' => 'float',
			'started_at' => 'immutable_datetime',
			'finished_at' => 'immutable_datetime',
		];
	}
}
