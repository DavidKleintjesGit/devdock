<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectWorkflow extends Model
{
	protected $fillable = [
		'project',
		'max_rounds',
		'after_rounds',
		'context',
	];
}
