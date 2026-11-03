<?php

declare(strict_types=1);

namespace Src\Board\Models;

use Illuminate\Database\Eloquent\Model;

class CardView extends Model
{
	protected $fillable = [
		'card',
		'seen_at',
	];

	protected function casts(): array
	{
		return [
			'seen_at' => 'immutable_datetime',
		];
	}
}
