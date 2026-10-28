<?php

declare(strict_types=1);

namespace Src\Session\Models;

use Illuminate\Database\Eloquent\Model;

class ClaudeSession extends Model
{
	protected $fillable = [
		'uuid',
		'folder',
		'project',
		'title',
		'branch',
		'prompt_count',
		'bytes',
		'last_active_at',
		'fingerprint',
	];

	protected function casts(): array
	{
		return ['last_active_at' => 'immutable_datetime'];
	}
}
