<?php

declare(strict_types=1);

namespace Src\Worktree\Exceptions;

use Exception;

/**
 * Other work still runs in this working copy, and the two may not run side by side.
 */
class WorkingCopyBusyException extends Exception {}
