<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Library extends Model
{
	protected $table = 'libraries';

	protected $fillable = [
		'name',
		'folder',
		'skills_folder',
	];

	public function items(): HasMany
	{
		return $this->hasMany(LibraryItem::class);
	}

	public function followers(): HasMany
	{
		return $this->hasMany(LibraryFollower::class);
	}
}
