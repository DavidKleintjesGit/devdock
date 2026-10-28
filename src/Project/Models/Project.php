<?php

declare(strict_types=1);

namespace Src\Project\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
	protected $fillable = [
		'name',
		'path',
		'branch',
		'changed_files',
		'ahead',
		'behind',
		'remote',
		'scanned_at',
	];

	protected function casts(): array
	{
		return ['scanned_at' => 'immutable_datetime'];
	}
}
