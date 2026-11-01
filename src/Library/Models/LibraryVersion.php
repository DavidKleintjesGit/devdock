<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryVersion extends Model
{
	public const UPDATED_AT = null;

	protected $fillable = [
		'library_item_id',
		'number',
		'contents',
		'hash',
		'source',
		'note',
		'project',
		'created_at',
	];

	protected $casts = [
		'created_at' => 'immutable_datetime',
	];

	public function item(): BelongsTo
	{
		return $this->belongsTo(LibraryItem::class, 'library_item_id');
	}
}
