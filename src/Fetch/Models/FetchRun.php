<?php

declare(strict_types=1);

namespace Src\Fetch\Models;

use Illuminate\Database\Eloquent\Model;

class FetchRun extends Model
{
	protected $fillable = [
		'status',
		'source',
		'results',
		'message',
		'started_at',
		'finished_at',
	];

	protected function casts(): array
	{
		return [
			'results' => 'array',
			'started_at' => 'immutable_datetime',
			'finished_at' => 'immutable_datetime',
		];
	}
}
