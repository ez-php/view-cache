<?php

declare(strict_types=1);

namespace EzPhp\ViewCache;

use EzPhp\Cache\CacheInterface;

/**
 * Class CacheViewCacheStore
 *
 * Keeps entries in any ez-php/cache driver (Redis, Memcached, APCu-backed, …),
 * so several servers share rendered output. ez-php/cache is a soft dependency
 * (`suggest`); this class is only loaded when used.
 *
 * @package EzPhp\ViewCache
 */
final readonly class CacheViewCacheStore implements ViewCacheStoreInterface
{
    /**
     * @param CacheInterface $cache
     * @param int            $ttl    Seconds an entry lives; 0 = until evicted. Stale entries are
     *                               detected by mtime anyway — the TTL only bounds memory.
     * @param string         $prefix Key namespace inside the cache.
     */
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 0,
        private string $prefix = 'view-cache:',
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ?string
    {
        $entry = $this->cache->get($this->prefix . $key);

        return is_string($entry) ? $entry : null;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $key, string $entry): void
    {
        $this->cache->set($this->prefix . $key, $entry, $this->ttl);
    }
}
