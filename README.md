# ez-php/view-cache

Output-caching decorator for [`ez-php/view`](https://github.com/ez-php/view)'s `ViewEngine`. Caches a
rendered template's output to a file, keyed by template name and render data, and invalidates it based on
the source template file's mtime.

---

## Installation

```bash
composer require ez-php/view-cache
```

---

## Usage

```php
use EzPhp\View\ViewEngine;
use EzPhp\ViewCache\CachingViewEngine;

$viewPath = __DIR__ . '/resources/views';
$engine = new CachingViewEngine(
    new ViewEngine($viewPath),
    $viewPath,
    __DIR__ . '/storage/view-cache',
);

echo $engine->render('home', ['title' => 'Welcome']);
```

`render()` mirrors `ViewEngine::render()`'s signature, so `CachingViewEngine` is a drop-in substitute
anywhere a plain `ViewEngine` is used directly. A cached entry is served back as long as the source
template file's mtime hasn't changed since it was written; editing the template invalidates it
automatically.

### Invalidating on partial and layout changes

By default only the top-level template's mtime is checked, so editing a partial or layout alone does not
refresh a cached parent. Pass `trackDependencies: true` to record every layout and partial a render pulls in
(recursively) and re-render when any of them changes:

```php
$engine = new CachingViewEngine(new ViewEngine($viewPath), $viewPath, $cachePath, trackDependencies: true);
```

Requires an `ez-php/view` release that provides `ViewEngine::onResolve()`.

Wiring this into an application's dependency container (e.g. binding `CachingViewEngine` instead of
`ViewEngine` behind an environment flag) is left to the application — this module has no framework
dependency beyond `ez-php/view` itself.

---

## License

MIT
