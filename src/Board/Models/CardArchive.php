<?php

declare(strict_types=1);

namespace Src\Board\Models;

use Illuminate\Database\Eloquent\Model;

class CardArchive extends Model
{
	protected $fillable = [
		'card',
		'project',
		'branch',
		'agent_session_uuid',
		'cleaned_up_at',
		'archived_at',
		'resurface_signature',
	];

	protected function casts(): array
	{
		return [
			'cleaned_up_at' => 'immutable_datetime',
			'archived_at' => 'immutable_datetime',
		];
	}
}
