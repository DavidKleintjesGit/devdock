<?php

declare(strict_types=1);

namespace Src\Project\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
	/** @use HasFactory<ProjectFactory> */
	use HasFactory;

	protected $fillable = [
		'name',
		'path',
		'branch',
		'changed_files',
		'ahead',
		'behind',
		'remote',
		'scanned_at',
	];

	protected function casts(): array
	{
		return ['scanned_at' => 'immutable_datetime'];
	}
}
