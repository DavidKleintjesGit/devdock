<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Database\Factories\ReviewFindingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ReviewFindingFactory::class)]
class ReviewFinding extends Model
{
	/** @use HasFactory<ReviewFindingFactory> */
	use HasFactory;

	protected $fillable = [
		'card',
		'lens_id',
		'number',
		'location',
		'text',
		'status',
		'reason',
		'first_review_round_id',
		'last_review_round_id',
		'resolved_review_round_id',
	];

	public function lens(): BelongsTo
	{
		return $this->belongsTo(Lens::class);
	}

	public function firstReviewRound(): BelongsTo
	{
		return $this->belongsTo(ReviewRound::class, 'first_review_round_id');
	}

	public function resolvedReviewRound(): BelongsTo
	{
		return $this->belongsTo(ReviewRound::class, 'resolved_review_round_id');
	}
}
