<?php

declare(strict_types=1);

namespace Src\Packages\Process;

use Illuminate\Contracts\Process\ProcessResult;
use RuntimeException;

/**
 * What a LocalCommand gave back, in the shape the Process facade gives it, so
 * code reading a result does not care which of the two ran.
 */
class LocalCommandResult implements ProcessResult
{
	/**
	 * @param  array<int, string>  $command
	 */
	public function __construct(
		private readonly array $command,
		private readonly int $exitCode,
		private readonly string $output,
		private readonly string $errorOutput,
	) {}

	public function command(): string
	{
		return implode(' ', $this->command);
	}

	public function successful(): bool
	{
		return $this->exitCode === 0;
	}

	public function failed(): bool
	{
		return ! $this->successful();
	}

	public function exitCode(): int
	{
		return $this->exitCode;
	}

	public function output(): string
	{
		return $this->output;
	}

	public function seeInOutput(string $output): bool
	{
		return str_contains($this->output, $output);
	}

	public function errorOutput(): string
	{
		return $this->errorOutput;
	}

	public function seeInErrorOutput(string $output): bool
	{
		return str_contains($this->errorOutput, $output);
	}

	public function throw(?callable $callback = null): static
	{
		if ($this->successful()) {
			return $this;
		}

		if ($callback !== null) {
			$callback($this, new RuntimeException($this->errorOutput));
		}

		throw new RuntimeException('Command failed: ' . $this->command() . "\n" . $this->errorOutput);
	}

	public function throwIf(bool $condition, ?callable $callback = null): static
	{
		return $condition ? $this->throw($callback) : $this;
	}
}
