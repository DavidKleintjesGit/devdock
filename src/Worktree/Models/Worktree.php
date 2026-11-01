<?php

declare(strict_types=1);

namespace Src\Worktree\Models;

use Illuminate\Database\Eloquent\Model;
use Src\Worktree\Enums\WorktreeOriginEnum;

class Worktree extends Model
{
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
