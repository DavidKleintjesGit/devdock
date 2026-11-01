<?php

declare(strict_types=1);

namespace Src\Library\Models;

use Illuminate\Database\Eloquent\Model;

class RuleLanding extends Model
{
	protected $table = 'rule_landings';

	protected $fillable = ['project', 'kind', 'path'];
}
