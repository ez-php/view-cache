<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Cache\ArrayDriver;
use EzPhp\View\ViewEngine;
use EzPhp\ViewCache\CacheViewCacheStore;
use EzPhp\ViewCache\CachingViewEngine;
use EzPhp\ViewCache\FileViewCacheStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * CachingViewEngine over an ez-php/cache store instead of files.
 *
 * @package Tests
 */
#[CoversClass(CacheViewCacheStore::class)]
#[CoversClass(FileViewCacheStore::class)]
#[CoversClass(CachingViewEngine::class)]
final class ViewCacheStoreTest extends TestCase
{
    private string $viewPath;

    protected function setUp(): void
    {
        $this->viewPath = sys_get_temp_dir() . '/ez-view-cache-store-' . bin2hex(random_bytes(4));
        mkdir($this->viewPath, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->viewPath . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->viewPath);
    }

    public function test_entries_are_served_from_the_cache_store(): void
    {
        $cache = new ArrayDriver();
        $engine = new CachingViewEngine(new ViewEngine($this->viewPath), $this->viewPath, new CacheViewCacheStore($cache, prefix: 'v:'));
        $path = $this->viewPath . '/hello.php';
        file_put_contents($path, '<?= "first" ?>');
        $mtime = (int) filemtime($path);

        $cold = $engine->render('hello');
        self::assertSame('first', $cold);

        // Same mtime, different source: the cached output is served.
        file_put_contents($path, '<?= "second" ?>');
        touch($path, $mtime);
        clearstatcache();

        $warm = $engine->render('hello');
        self::assertSame('first', $warm);
        self::assertTrue($cache->has('v:' . hash('sha256', 'hello|' . serialize([]))));
    }

    public function test_a_changed_mtime_invalidates_a_cache_store_entry(): void
    {
        $engine = new CachingViewEngine(new ViewEngine($this->viewPath), $this->viewPath, new CacheViewCacheStore(new ArrayDriver()));
        $path = $this->viewPath . '/hello.php';
        file_put_contents($path, '<?= "first" ?>');
        $engine->render('hello');

        file_put_contents($path, '<?= "second" ?>');
        touch($path, time() + 10);
        clearstatcache();

        self::assertSame('second', $engine->render('hello'));
    }

    public function test_file_store_round_trip(): void
    {
        $dir = $this->viewPath . '/store';
        $store = new FileViewCacheStore($dir);

        self::assertNull($store->get('abc'));
        $store->put('abc', 'entry');
        self::assertSame('entry', $store->get('abc'));

        unlink($dir . '/abc.cache');
        rmdir($dir);
    }
}
