<?php

declare(strict_types=1);

namespace Src\Worktree\Models;

use Illuminate\Database\Eloquent\Model;

class Worktree extends Model
{
	protected $fillable = [
		'project',
		'branch',
		'path',
		'site',
		'card',
	];
}
