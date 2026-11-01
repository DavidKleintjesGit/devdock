<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
	protected $table = 'favorites';

	protected $fillable = ['kind', 'project', 'path'];
}
