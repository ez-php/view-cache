<?php

declare(strict_types=1);

namespace EzPhp\ViewCache;

use EzPhp\View\ViewException;

/**
 * Class FileViewCacheStore
 *
 * One `<key>.cache` file per entry under a directory — the default store, and
 * what a `string $cache` given to CachingViewEngine becomes.
 *
 * @package EzPhp\ViewCache
 */
final readonly class FileViewCacheStore implements ViewCacheStoreInterface
{
    /**
     * @param string $cachePath Directory entries are written to (created on first write).
     */
    public function __construct(private string $cachePath)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ?string
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $entry = file_get_contents($file);

        return $entry === false ? null : $entry;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ViewException When the cache directory cannot be created or the entry
     *                       cannot be written — reported rather than silently
     *                       re-rendering every request against an unwritable cache.
     */
    public function put(string $key, string $entry): void
    {
        $dir = rtrim($this->cachePath, '/\\');

        // Another process may create the directory between is_dir() and mkdir().
        if (!is_dir($dir) && !self::attempt(static fn (): bool => mkdir($dir, 0o755, true), $error) && !is_dir($dir)) {
            throw new ViewException("Cannot create view cache directory {$dir}: {$error}");
        }

        $path = $this->path($key);

        if (!self::attempt(static fn (): bool => file_put_contents($path, $entry, LOCK_EX) !== false, $error)) {
            throw new ViewException("Cannot write view cache entry {$path}: {$error}");
        }
    }

    /**
     * Run a filesystem call with a scoped error handler instead of `@`, so the
     * PHP warning becomes the exception message instead of being lost.
     *
     * @param callable(): bool $operation
     * @param string|null      $error     Set to the captured warning, or 'unknown error'.
     *
     * @param-out string $error
     *
     * @return bool
     */
    private static function attempt(callable $operation, ?string &$error): bool
    {
        $error = 'unknown error';

        set_error_handler(static function (int $errno, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->cachePath, '/\\') . \DIRECTORY_SEPARATOR . $key . '.cache';
    }
}
