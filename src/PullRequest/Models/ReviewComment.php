<?php

declare(strict_types=1);

namespace Src\PullRequest\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewComment extends Model
{
	protected $fillable = [
		'pull_request_id',
		'comment_id',
		'parent_comment_id',
		'author',
		'body',
		'path',
		'line',
		'is_prompt',
		'url',
		'commented_at',
	];

	protected function casts(): array
	{
		return [
			'is_prompt' => 'boolean',
			'commented_at' => 'immutable_datetime',
		];
	}
}
