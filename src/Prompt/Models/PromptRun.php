<?php

declare(strict_types=1);

namespace Src\Prompt\Models;

use Database\Factories\PromptRunFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(PromptRunFactory::class)]
class PromptRun extends Model
{
	/** @use HasFactory<PromptRunFactory> */
	use HasFactory;

	protected $fillable = [
		'card',
		'review_round_id',
		'project',
		'branch',
		'directory',
		'ticket_key',
		'session_uuid',
		'claims_session',
		'git_grant',
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
