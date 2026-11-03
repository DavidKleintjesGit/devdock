<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Database\Factories\ReviewRoundFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ReviewRoundFactory::class)]
class ReviewRound extends Model
{
	/** @use HasFactory<ReviewRoundFactory> */
	use HasFactory;

	protected $fillable = [
		'card',
		'number',
		'series_start',
		'commit_sha',
		'fingerprint',
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
