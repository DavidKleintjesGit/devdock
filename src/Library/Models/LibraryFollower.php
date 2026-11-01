<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryFollower extends Model
{
	protected $table = 'library_followers';

	protected $fillable = ['library_id', 'project'];
}
