<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryItem extends Model
{
	protected $fillable = [
		'library_id',
		'kind',
		'name',
	];

	public function library(): BelongsTo
	{
		return $this->belongsTo(Library::class);
	}

	public function versions(): HasMany
	{
		return $this->hasMany(LibraryVersion::class)->orderBy('number');
	}
}
