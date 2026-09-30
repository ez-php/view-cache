<?php

declare(strict_types=1);

namespace EzPhp\ViewCache;

use EzPhp\View\ViewEngine;

/**
 * Class CachingViewEngine
 *
 * Decorates a `ViewEngine` with an output cache: a rendered template is
 * stored under a key derived from template name and render data, and served
 * back on the next call as long as the source template's mtime has not
 * changed since the entry was written. Entries live in a ViewCacheStoreInterface
 * — files by default, or any ez-php/cache driver via CacheViewCacheStore.
 *
 * @package EzPhp\ViewCache
 */
final class CachingViewEngine
{
    private readonly ViewCacheStoreInterface $store;

    /**
     * @param ViewEngine $engine    The underlying engine that performs the actual render.
     * @param string     $viewPath  Absolute path to the directory containing template files — must match the
     *                              path `$engine` was constructed with, so mtime checks see the real source file.
     * @param string|ViewCacheStoreInterface $cache A directory for file entries (FileViewCacheStore),
     *                                             or any store — e.g. CacheViewCacheStore over Redis.
     * @param bool       $trackDependencies When true, every template file resolved during a render (layouts and
     *                                      partials, recursively) is recorded and its mtime checked on later
     *                                      hits, so editing a partial or layout invalidates the entry. Off by
     *                                      default: only the top-level template's mtime is checked.
     */
    public function __construct(
        private readonly ViewEngine $engine,
        private readonly string $viewPath,
        string|ViewCacheStoreInterface $cache,
        private readonly bool $trackDependencies = false,
    ) {
        $this->store = is_string($cache) ? new FileViewCacheStore($cache) : $cache;
    }

    /**
     * Render a template, returning a cached copy when the source template's
     * mtime matches the mtime recorded in the cache entry.
     *
     * @param string               $template Template name in dot-notation.
     * @param array<string, mixed> $data     Variables made available inside the template as `$name`;
     *                                       must contain only values `serialize()` can round-trip.
     *
     * @return string
     */
    public function render(string $template, array $data = []): string
    {
        $sourcePath = $this->resolveSourcePath($template);

        if (!is_file($sourcePath)) {
            return $this->engine->render($template, $data);
        }

        $sourceMtime = (int) filemtime($sourcePath);
        $key = $this->cacheKey($template, $data);

        $cached = $this->readCache($key, $sourceMtime);

        if ($cached !== null) {
            return $cached;
        }

        $dependencies = [];

        if ($this->trackDependencies) {
            $this->engine->onResolve(static function (string $path) use (&$dependencies): void {
                $dependencies[$path] = (int) filemtime($path);
            });
        }

        try {
            $output = $this->engine->render($template, $data);
        } finally {
            if ($this->trackDependencies) {
                $this->engine->onResolve(null);
            }
        }

        $this->store->put($key, serialize(['mtime' => $sourceMtime, 'output' => $output, 'dependencies' => $dependencies]));

        return $output;
    }

    /**
     * Resolve a dot-notation template name to its source file path, mirroring
     * `ViewEngine`'s own (private) resolution rule.
     *
     * @param string $template Template name in dot-notation.
     *
     * @return string
     */
    private function resolveSourcePath(string $template): string
    {
        $relative = str_replace('.', \DIRECTORY_SEPARATOR, $template);

        return rtrim($this->viewPath, '/\\') . \DIRECTORY_SEPARATOR . $relative . '.php';
    }

    /**
     * The store key for a template + data combination.
     *
     * @param string               $template Template name in dot-notation.
     * @param array<string, mixed> $data     Render data.
     *
     * @return string
     */
    private function cacheKey(string $template, array $data): string
    {
        return hash('sha256', $template . '|' . serialize($data));
    }

    /**
     * Read an entry, returning its output only if the recorded mtime still
     * matches the source template's current mtime (and, when tracked, every
     * dependency's). Objects in an entry are never instantiated.
     *
     * @param string $key         Store key.
     * @param int    $sourceMtime Current mtime of the source template.
     *
     * @return string|null
     */
    private function readCache(string $key, int $sourceMtime): ?string
    {
        $raw = $this->store->get($key);

        if ($raw === null) {
            return null;
        }

        /** @var mixed $entry */
        $entry = unserialize($raw, ['allowed_classes' => false]);

        if (!is_array($entry) || ($entry['mtime'] ?? null) !== $sourceMtime) {
            return null;
        }

        $dependencies = $entry['dependencies'] ?? [];

        if (is_array($dependencies)) {
            foreach ($dependencies as $path => $mtime) {
                if (!is_string($path) || !is_file($path) || (int) filemtime($path) !== $mtime) {
                    return null;
                }
            }
        }

        $output = $entry['output'] ?? null;

        return is_string($output) ? $output : null;
    }
}
