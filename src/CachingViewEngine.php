<?php

declare(strict_types=1);

namespace EzPhp\ViewCache;

use EzPhp\View\ViewEngine;

/**
 * Class CachingViewEngine
 *
 * Decorates a `ViewEngine` with an output cache: a rendered template is
 * written to a cache file keyed by template name and render data, and
 * served back on the next call as long as the source template's mtime has
 * not changed since the entry was written.
 *
 * @package EzPhp\ViewCache
 */
final class CachingViewEngine
{
    /**
     * @param ViewEngine $engine    The underlying engine that performs the actual render.
     * @param string     $viewPath  Absolute path to the directory containing template files — must match the
     *                              path `$engine` was constructed with, so mtime checks see the real source file.
     * @param string     $cachePath Absolute path to the directory cache entries are written to.
     */
    public function __construct(
        private readonly ViewEngine $engine,
        private readonly string $viewPath,
        private readonly string $cachePath,
    ) {
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
        $cacheFile = $this->cacheFilePath($template, $data);

        $cached = $this->readCache($cacheFile, $sourceMtime);

        if ($cached !== null) {
            return $cached;
        }

        $output = $this->engine->render($template, $data);
        $this->writeCache($cacheFile, $sourceMtime, $output);

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
     * Derive the cache file path for a template + data combination.
     *
     * @param string               $template Template name in dot-notation.
     * @param array<string, mixed> $data     Render data.
     *
     * @return string
     */
    private function cacheFilePath(string $template, array $data): string
    {
        $key = hash('sha256', $template . '|' . serialize($data));

        return rtrim($this->cachePath, '/\\') . \DIRECTORY_SEPARATOR . $key . '.cache';
    }

    /**
     * Read a cache entry, returning its output only if the recorded mtime
     * still matches the source template's current mtime.
     *
     * @param string $cacheFile   Path to the cache entry file.
     * @param int    $sourceMtime Current mtime of the source template.
     *
     * @return string|null
     */
    private function readCache(string $cacheFile, int $sourceMtime): ?string
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        /** @var mixed $entry */
        $entry = unserialize((string) file_get_contents($cacheFile));

        if (!is_array($entry) || ($entry['mtime'] ?? null) !== $sourceMtime) {
            return null;
        }

        $output = $entry['output'] ?? null;

        return is_string($output) ? $output : null;
    }

    /**
     * Write a cache entry, creating the cache directory if it doesn't exist yet.
     *
     * @param string $cacheFile   Path to the cache entry file.
     * @param int    $sourceMtime Mtime of the source template at render time.
     * @param string $output      Rendered output to cache.
     *
     * @return void
     */
    private function writeCache(string $cacheFile, int $sourceMtime, string $output): void
    {
        $dir = dirname($cacheFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($cacheFile, serialize(['mtime' => $sourceMtime, 'output' => $output]));
    }
}
