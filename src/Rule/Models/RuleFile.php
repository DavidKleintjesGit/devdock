<?php

declare(strict_types=1);

namespace Src\Rule\Models;

use Illuminate\Database\Eloquent\Model;

class RuleFile extends Model
{
	protected $fillable = [
		'project',
		'path',
		'group',
		'origin',
		'lines',
		'words',
		'bytes',
		'core_file',
		'core_lines',
		'scanned_at',
	];

	protected function casts(): array
	{
		return ['scanned_at' => 'immutable_datetime'];
	}
}
