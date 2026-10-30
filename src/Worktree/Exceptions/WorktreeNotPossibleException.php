<?php

declare(strict_types=1);

namespace Src\Worktree\Exceptions;

use Exception;

/**
 * A working copy for this branch could not be made, and the reason is one a
 * person has to act on.
 */
class WorktreeNotPossibleException extends Exception {}
