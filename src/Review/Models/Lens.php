<?php

declare(strict_types=1);

namespace Src\Review\Models;

use Database\Factories\LensFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LensFactory::class)]
class Lens extends Model
{
	/** @use HasFactory<LensFactory> */
	use HasFactory;

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
