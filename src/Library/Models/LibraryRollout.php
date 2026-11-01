<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryRollout extends Model
{
	public const UPDATED_AT = null;

	protected $fillable = [
		'library_version_id',
		'project',
		'path',
		'backup',
		'created_at',
	];

	protected $casts = [
		'created_at' => 'immutable_datetime',
	];

	public function version(): BelongsTo
	{
		return $this->belongsTo(LibraryVersion::class, 'library_version_id');
	}
}
