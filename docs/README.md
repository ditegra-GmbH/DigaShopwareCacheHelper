# DigaShopwareCacheHelper documentation

Cache Helper logs what the Shopware HTTP cache does: which pages are served from cache, which are written with which tags, how cache keys are built, what gets invalidated, and which plugin caused an invalidation. It changes nothing about caching itself.

Supported: Shopware 6.7 (`shopware/core ~6.7.0`).

| Document | Contents |
| --- | --- |
| [Configuration](configuration.md) | Every setting in the plugin config and what it switches |
| [Log reference](log-reference.md) | Where the logs go and how to read each log line |
| [Invalidation origin](invalidation-origin.md) | Which plugin caused an invalidation and where it was triggered |
| [Architecture](architecture.md) | Services, event flow, and the Shopware hooks the plugin uses |
| [Development](development.md) | Setup, tests, static analysis, build, release, CI |

## Quick start

1. Install and activate the plugin: `bin/console plugin:install --activate DigaShopwareCacheHelper`
2. In the Administration, open *Extensions > My extensions > Cache Helper > Configure*.
3. Under *Logging output*, choose *Log with Cachehelper logger* to get a separate file, `var/log/diga_cache_helper-<env>-<date>.log`.
4. Enable the events you want to watch, then reproduce the behaviour in the storefront or admin.

Everything except the invalidation origin is enabled by default. On a busy production shop, enable only what you need; see [Performance](configuration.md#performance).
