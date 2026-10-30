<?php

declare(strict_types=1);

namespace Src\Ticket\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
	protected $fillable = [
		'key',
		'summary',
		'description',
		'status',
		'type',
		'priority',
		'url',
		'changed_at',
	];

	protected function casts(): array
	{
		return ['changed_at' => 'immutable_datetime'];
	}
}
