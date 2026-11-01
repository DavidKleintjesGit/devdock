<?php

declare(strict_types=1);

namespace Src\Session\Models;

use Illuminate\Database\Eloquent\Model;

class ClaudeSession extends Model
{
	protected $fillable = [
		'uuid',
		'folder',
		'cwd',
		'project',
		'title',
		'branch',
		'prompt_count',
		'bytes',
		'last_active_at',
		'first_active_at',
		'active_days',
		'has_commit',
		'is_interrupted',
		'is_unanswered',
		'is_pinned',
		'fingerprint',
	];

	protected function casts(): array
	{
		return [
			'last_active_at' => 'immutable_datetime',
			'first_active_at' => 'immutable_datetime',
			'has_commit' => 'boolean',
			'is_interrupted' => 'boolean',
			'is_unanswered' => 'boolean',
			'is_pinned' => 'boolean',
		];
	}
}
