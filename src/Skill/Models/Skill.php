<?php

declare(strict_types=1);

namespace Src\Skill\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
	protected $fillable = [
		'name',
		'scope',
		'project',
		'plugin',
		'description',
		'path',
		'lines',
		'scanned_at',
	];

	protected function casts(): array
	{
		return ['scanned_at' => 'immutable_datetime'];
	}
}
