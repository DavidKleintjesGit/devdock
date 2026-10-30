<?php

declare(strict_types=1);

namespace Src\Task\Models;

use Illuminate\Database\Eloquent\Model;

class TaskAgent extends Model
{
	protected $fillable = [
		'task_id',
		'session_uuid',
		'name',
		'directory',
		'is_active',
		'started_at',
	];

	protected $casts = [
		'is_active' => 'boolean',
		'started_at' => 'immutable_datetime',
	];
}
