<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\View\ViewEngine;
use EzPhp\ViewCache\CachingViewEngine;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class CachingViewEngineTest
 *
 * @package Tests
 */
#[CoversClass(CachingViewEngine::class)]
final class CachingViewEngineTest extends TestCase
{
    private string $viewPath;

    private string $cachePath;

    private CachingViewEngine $cachingEngine;

    protected function setUp(): void
    {
        $this->viewPath = sys_get_temp_dir() . '/view-cache-views-' . uniqid('', true);
        $this->cachePath = sys_get_temp_dir() . '/view-cache-store-' . uniqid('', true);

        mkdir($this->viewPath, 0o755, true);
        mkdir($this->cachePath, 0o755, true);

        $this->cachingEngine = new CachingViewEngine(new ViewEngine($this->viewPath), $this->viewPath, $this->cachePath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->viewPath);
        $this->removeDirectory($this->cachePath);
    }

    public function test_it_renders_a_template_on_a_cold_cache(): void
    {
        $this->writeTemplate('greeting', '<?= "Hello, " . $this->e($name) . "!" ?>');

        $output = $this->cachingEngine->render('greeting', ['name' => 'World']);

        self::assertSame('Hello, World!', $output);
    }

    public function test_it_serves_stale_content_from_cache_when_the_template_mtime_is_unchanged(): void
    {
        $path = $this->writeTemplate('greeting', '<?= "first" ?>');
        $this->cachingEngine->render('greeting');

        $mtime = (int) filemtime($path);
        file_put_contents($path, '<?= "second" ?>');
        touch($path, $mtime);

        $output = $this->cachingEngine->render('greeting');

        self::assertSame('first', $output);
    }

    public function test_it_invalidates_the_cache_when_the_template_mtime_changes(): void
    {
        $path = $this->writeTemplate('greeting', '<?= "first" ?>');
        $this->cachingEngine->render('greeting');

        $originalMtime = (int) filemtime($path);
        file_put_contents($path, '<?= "second" ?>');
        touch($path, $originalMtime + 5);

        $output = $this->cachingEngine->render('greeting');

        self::assertSame('second', $output);
    }

    public function test_it_caches_different_data_for_the_same_template_separately(): void
    {
        $this->writeTemplate('greeting', '<?= "Hello, " . $this->e($name) . "!" ?>');

        $first = $this->cachingEngine->render('greeting', ['name' => 'Alice']);
        $second = $this->cachingEngine->render('greeting', ['name' => 'Bob']);

        self::assertSame('Hello, Alice!', $first);
        self::assertSame('Hello, Bob!', $second);
    }

    public function test_it_falls_through_to_the_underlying_engine_for_a_missing_template(): void
    {
        $this->expectException(\EzPhp\View\ViewException::class);

        $this->cachingEngine->render('missing');
    }

    // ── dependency tracking (opt-in) ──────────────────────────────────────────

    private function trackingEngine(): CachingViewEngine
    {
        return new CachingViewEngine(new ViewEngine($this->viewPath), $this->viewPath, $this->cachePath, trackDependencies: true);
    }

    private function changeContents(string $path, string $contents): void
    {
        $mtime = (int) filemtime($path);
        file_put_contents($path, $contents);
        touch($path, $mtime + 5);
    }

    public function test_default_mode_ignores_a_changed_partial(): void
    {
        $this->writeTemplate('page', '<?= $this->partial("part") ?>');
        $partial = $this->writeTemplate('part', 'v1');
        $this->cachingEngine->render('page');

        $this->changeContents($partial, 'v2');

        self::assertSame('v1', $this->cachingEngine->render('page'));
    }

    public function test_tracking_mode_invalidates_when_a_partial_changes(): void
    {
        $engine = $this->trackingEngine();
        $this->writeTemplate('page', '<?= $this->partial("part") ?>');
        $partial = $this->writeTemplate('part', 'v1');
        self::assertSame('v1', $engine->render('page'));

        $this->changeContents($partial, 'v2');

        self::assertSame('v2', $engine->render('page'));
    }

    public function test_tracking_mode_invalidates_when_a_layout_changes(): void
    {
        $engine = $this->trackingEngine();
        $layout = $this->writeTemplate('layout', '[<?= $this->yield("body") ?>]');
        $this->writeTemplate('page', '<?php $this->extends("layout") ?><?php $this->section("body") ?>b<?php $this->endSection() ?>');
        self::assertSame('[b]', $engine->render('page'));

        $this->changeContents($layout, '{<?= $this->yield("body") ?>}');

        self::assertSame('{b}', $engine->render('page'));
    }

    public function test_tracking_mode_invalidates_when_a_nested_partial_changes(): void
    {
        $engine = $this->trackingEngine();
        $this->writeTemplate('page', '<?= $this->partial("outer") ?>');
        $this->writeTemplate('outer', '<?= $this->partial("inner") ?>');
        $inner = $this->writeTemplate('inner', 'v1');
        $engine->render('page');

        $this->changeContents($inner, 'v2');

        self::assertSame('v2', $engine->render('page'));
    }

    public function test_tracking_mode_serves_the_cache_when_nothing_changed(): void
    {
        $engine = $this->trackingEngine();
        $this->writeTemplate('page', '<?= $this->partial("part") ?>');
        $partial = $this->writeTemplate('part', 'v1');
        $engine->render('page');

        $mtime = (int) filemtime($partial);
        file_put_contents($partial, 'v2');
        touch($partial, $mtime);

        self::assertSame('v1', $engine->render('page'));
    }

    public function test_tracking_mode_invalidates_when_a_dependency_is_deleted(): void
    {
        $engine = $this->trackingEngine();
        $this->writeTemplate('page', '<?= $this->partial("part") ?>');
        $partial = $this->writeTemplate('part', 'v1');
        $engine->render('page');

        unlink($partial);

        $this->expectException(\EzPhp\View\ViewException::class);
        $engine->render('page');
    }

    private function writeTemplate(string $name, string $contents): string
    {
        $path = $this->viewPath . '/' . $name . '.php';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_it_does_not_instantiate_objects_found_in_a_cache_entry(): void
    {
        $path = $this->writeTemplate('greeting', '<?= "fresh" ?>');
        $this->cachingEngine->render('greeting');

        $files = glob($this->cachePath . '/*.cache');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        CacheEntryGadget::$woken = false;
        file_put_contents($files[0], serialize([
            'mtime' => (int) filemtime($path),
            'output' => new CacheEntryGadget(),
            'dependencies' => [],
        ]));

        $output = $this->cachingEngine->render('greeting');

        self::assertSame('fresh', $output);
        self::assertFalse(CacheEntryGadget::$woken);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}

/**
 * Fixture whose `__wakeup()` records that it was unserialized.
 */
final class CacheEntryGadget
{
    public static bool $woken = false;

    public function __wakeup(): void
    {
        self::$woken = true;
    }
}
