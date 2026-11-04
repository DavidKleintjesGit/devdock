<?php

declare(strict_types=1);

namespace Src\PullRequest\Models;

use Database\Factories\PullRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(PullRequestFactory::class)]
class PullRequest extends Model
{
	/** @use HasFactory<PullRequestFactory> */
	use HasFactory;

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
