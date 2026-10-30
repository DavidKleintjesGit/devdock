<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewResult extends Model
{
	protected $fillable = [
		'review_round_id',
		'lens_id',
		'verdict',
		'findings',
		'report',
		'touched_files',
		'session_uuid',
		'activity',
		'tool_calls',
		'cost',
		'started_at',
		'last_seen_at',
		'finished_at',
	];

	protected $casts = [
		'started_at' => 'immutable_datetime',
		'last_seen_at' => 'immutable_datetime',
		'finished_at' => 'immutable_datetime',
	];

	public function lens(): BelongsTo
	{
		return $this->belongsTo(Lens::class);
	}

	public function round(): BelongsTo
	{
		return $this->belongsTo(ReviewRound::class);
	}
}
