<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Illuminate\Database\Eloquent\Model;

class LensSetting extends Model
{
	protected $fillable = [
		'lens_id',
		'project',
		'is_enabled',
		'paths',
		'rules',
		'command',
	];

	protected $casts = [
		'is_enabled' => 'boolean',
		'paths' => 'array',
		'rules' => 'array',
	];
}
