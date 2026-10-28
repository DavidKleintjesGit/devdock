<?php

declare(strict_types=1);

namespace Src\PullRequest\Models;

use Illuminate\Database\Eloquent\Model;

class PullRequest extends Model
{
	protected $fillable = [
		'repository',
		'number',
		'title',
		'state',
		'draft',
		'author',
		'my_role',
		'source_branch',
		'destination_branch',
		'approvals',
		'reviewers',
		'changes_requested_by',
		'comment_count',
		'url',
		'changed_at',
	];

	protected function casts(): array
	{
		return [
			'draft' => 'boolean',
			'changed_at' => 'immutable_datetime',
		];
	}
}
