<?php

declare(strict_types=1);

namespace Src\Board\Models;

use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
	protected $fillable = [
		'title',
		'note',
		'project',
		'done_at',
	];

	protected function casts(): array
	{
		return ['done_at' => 'immutable_datetime'];
	}
}
