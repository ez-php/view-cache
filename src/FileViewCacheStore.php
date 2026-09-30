<?php

declare(strict_types=1);

namespace EzPhp\ViewCache;

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

    public function get(string $key): ?string
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $entry = file_get_contents($file);

        return $entry === false ? null : $entry;
    }

    public function put(string $key, string $entry): void
    {
        $dir = rtrim($this->cachePath, '/\\');

        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        file_put_contents($this->path($key), $entry, LOCK_EX);
    }

    private function path(string $key): string
    {
        return rtrim($this->cachePath, '/\\') . \DIRECTORY_SEPARATOR . $key . '.cache';
    }
}
