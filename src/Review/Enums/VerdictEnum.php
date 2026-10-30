<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * What one lens made of a round. A lens that was called writes its verdict on
 * the first line of its report; a lens whose paths were not touched never ran.
 */
enum VerdictEnum: string
{
	case WAITING = 'waiting';
	case RUNNING = 'running';
	case PASS = 'pass';
	case FAIL = 'fail';
	case SKIPPED = 'skipped';
	case ERROR = 'error';

	public function label(): string
	{
		return match ($this) {
			self::WAITING => __('WACHT'),
			self::RUNNING => __('BEZIG'),
			self::PASS => 'PASS',
			self::FAIL => 'FAIL',
			self::SKIPPED => __('OVERGESLAGEN'),
			self::ERROR => __('VASTGELOPEN'),
		};
	}

	/**
	 * The colour of the square in front of a lens.
	 */
	public function tone(): string
	{
		return match ($this) {
			self::WAITING => 'wait',
			self::RUNNING => 'run',
			self::PASS => 'pass',
			self::FAIL => 'fail',
			self::SKIPPED => 'skip',
			self::ERROR => 'fail',
		};
	}

	public function chipTone(): string
	{
		return match ($this) {
			self::PASS => 'green',
			self::FAIL, self::ERROR => 'red',
			self::RUNNING => 'amber',
			default => 'gray',
		};
	}

	/**
	 * Whether this lens is finished, whatever it found. A lens that fell over
	 * is finished too: the round may not wait on something that is gone.
	 */
	public function isSettled(): bool
	{
		return in_array($this, [self::PASS, self::FAIL, self::SKIPPED, self::ERROR], true);
	}

	/**
	 * Whether this lens can be sent out again. Only something that went wrong
	 * is worth repeating; a verdict is a verdict.
	 */
	public function canBeRetried(): bool
	{
		return $this === self::ERROR;
	}
}
