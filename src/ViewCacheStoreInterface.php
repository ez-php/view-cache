<?php

declare(strict_types=1);

namespace EzPhp\ViewCache;

/**
 * Interface ViewCacheStoreInterface
 *
 * Where CachingViewEngine keeps its entries. An entry is an opaque string
 * (the serialized output plus the mtimes it depends on); validity is decided
 * by the engine, so a store only has to keep and return bytes.
 *
 * @package EzPhp\ViewCache
 */
interface ViewCacheStoreInterface
{
    /**
     * @param string $key Hex digest identifying template + data.
     *
     * @return string|null The stored entry, or null when absent.
     */
    public function get(string $key): ?string;

    /**
     * @param string $key
     * @param string $entry
     *
     * @return void
     */
    public function put(string $key, string $entry): void;
}
