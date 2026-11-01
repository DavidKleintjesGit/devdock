<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lens extends Model
{
	protected $table = 'lenses';

	protected $fillable = [
		'name',
		'description',
		'paths',
		'rules',
		'command',
		'position',
		'project',
	];

	protected $casts = [
		'paths' => 'array',
		'rules' => 'array',
	];

	public function settings(): HasMany
	{
		return $this->hasMany(LensSetting::class);
	}
}
