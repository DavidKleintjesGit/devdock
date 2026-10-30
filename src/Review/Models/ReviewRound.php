<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewRound extends Model
{
	protected $fillable = [
		'card',
		'number',
		'commit_sha',
		'directory',
		'status',
		'started_at',
		'finished_at',
	];

	protected $casts = [
		'started_at' => 'immutable_datetime',
		'finished_at' => 'immutable_datetime',
	];

	public function results(): HasMany
	{
		return $this->hasMany(ReviewResult::class);
	}
}
