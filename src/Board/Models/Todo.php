<?php

declare(strict_types=1);

namespace Src\Board\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(TodoFactory::class)]
class Todo extends Model
{
	/** @use HasFactory<TodoFactory> */
	use HasFactory;

	protected $fillable = [
		'title',
		'note',
		'project',
		'is_later',
		'done_at',
	];

	protected function casts(): array
	{
		return [
			'is_later' => 'boolean',
			'done_at' => 'immutable_datetime',
		];
	}
}
