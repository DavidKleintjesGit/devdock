<?php

declare(strict_types=1);

namespace Src\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
	protected $fillable = [
		'key',
	];

	public function links(): HasMany
	{
		return $this->hasMany(TaskLink::class);
	}

	public function agents(): HasMany
	{
		return $this->hasMany(TaskAgent::class);
	}
}
