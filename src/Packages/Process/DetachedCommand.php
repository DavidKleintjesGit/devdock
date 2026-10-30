<?php

declare(strict_types=1);

namespace Src\Packages\Process;

/**
 * An artisan command that has to outlive the request asking for it: fetching,
 * prompting and the lenses all run far longer than a page is willing to wait.
 */
class DetachedCommand
{
    public function artisan(string $command): void
    {
        // over the web the working folder is public/, so artisan gets its full path
        $line = sprintf(
            '%s %s %s',
            escapeshellarg($this->phpBinary()),
            escapeshellarg(base_path('artisan')),
            $command,
        );

        $handle = popen($this->cutLoose($line), 'r');

        if (is_resource($handle)) {
            pclose($handle);
        }
    }

    /**
     * The command line php. Over the web php runs as fpm or cgi, and that
     * binary cannot run artisan, so the one next to it is taken instead.
     */
    public function phpBinary(): string
    {
        if (filled(config('devdock.php_binary'))) {
            return (string) config('devdock.php_binary');
        }

        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        $name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $sibling = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.$name;

        return is_file($sibling) ? $sibling : 'php';
    }

    /**
     * Cutting loose from this request: on Windows through start, elsewhere by
     * sending the process to the background.
     */
    public function cutLoose(string $command): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'start /B "" '.$command;
        }

        return $command.' > /dev/null 2>&1 &';
    }
}
