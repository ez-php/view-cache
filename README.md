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

Wiring this into an application's dependency container (e.g. binding `CachingViewEngine` instead of
`ViewEngine` behind an environment flag) is left to the application — this module has no framework
dependency beyond `ez-php/view` itself.

---

## License

MIT
