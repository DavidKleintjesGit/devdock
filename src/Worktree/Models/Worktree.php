<?php

declare(strict_types=1);

namespace Src\Worktree\Models;

use Database\Factories\WorktreeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Src\Worktree\Enums\WorktreeOriginEnum;

#[UseFactory(WorktreeFactory::class)]
class Worktree extends Model
{
	/** @use HasFactory<WorktreeFactory> */
	use HasFactory;

	protected $fillable = [
		'project',
		'branch',
		'branch_created',
		'origin',
		'path',
		'site',
		'database',
		'card',
	];

	protected $casts = [
		'branch_created' => 'boolean',
		'origin' => WorktreeOriginEnum::class,
	];
}
