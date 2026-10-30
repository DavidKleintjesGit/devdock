<?php

declare(strict_types=1);

namespace Src\Task\Models;

use Illuminate\Database\Eloquent\Model;

class TaskLink extends Model
{
	protected $fillable = [
		'task_id',
		'kind',
		'value',
		'is_active',
	];

	protected $casts = [
		'is_active' => 'boolean',
	];
}
