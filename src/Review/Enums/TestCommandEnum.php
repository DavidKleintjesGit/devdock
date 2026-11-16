<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Which command a lens runs in a project.
 */
enum TestCommandEnum: string
{
	case AS_SET = 'as_set';
	case COMPOSER_TEST = 'composer_test';
}
