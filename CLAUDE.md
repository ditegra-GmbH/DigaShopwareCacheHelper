# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Shopware 6.7 plugin (`diga/shopwarecachehelper-plugin`, namespace `DigaShopwareCacheHelper\`) that logs HTTP cache events (cache hits, item writes with tags, cache key generation, cache/product invalidations) to help debug Shopware caching. It lives inside a Shopware installation at `custom/plugins/DigaShopwareCacheHelper`; the Shopware root is `/var/www/html` (many make targets `cd` there or use `../../../bin/console`).

User and developer documentation lives in `docs/` (configuration, log reference, invalidation origin, architecture, development). Keep it in sync when adding settings or log lines.

## Commands

All via the `makefile` (`make help` lists targets):

- `make dev` / `make prod` — composer install with / without dev deps
- `make stan` (alias `make validate`) — PHPStan level 5. The makefile passes `.` on the command line, so it analyses `src/` and `tests/` (excluding `src/Resources` and `vendor`). This is what CI runs.
- `make test` — PHPUnit unit tests in `tests/Unit`, bootstrapped by the plugin's own `vendor/autoload.php` (run `make dev` first; no Shopware kernel or database needed). Single test: `php ./vendor/bin/phpunit --configuration=./phpunit.xml --filter <TestName>`. Fixture plugins used to test plugin detection live in `tests/Fixture` under the `DigaTestFixture\` namespace. That namespace must stay outside `DigaShopwareCacheHelper\`, because the resolver skips the plugin's own frames.
- `make dev` puts a full `shopware/core` into the plugin's `vendor/`. Move it away before installing the plugin into the host shop at `/var/www/html`, so the core classes are not loaded twice.
- `make build` — refresh/install/activate the plugin in the host Shopware, build admin JS (`bin/build-js.sh`), and rewrite the "Supported SW Version" line in README.md with the current SW version
- `make zip` — writes `artifacts/DigaShopwareCacheHelper_<composer version>.zip` (runs `make clean` first; needs `jq`)
- `make release` — clean + build + zip

CI (`.github/workflows/`) runs in `ghcr.io/friendsofshopware/platform-plugin-dev:v6.7.0`: `make dev && make validate`, then `shopware-cli extension build .`, `make zip`, and `shopware-cli extension validate` on the zip. `pr-build-pipe.yml` runs on PRs. `release-pipe.yml` tags `v<composer.json version>` and uses the matching `## [x.y.z]` section of CHANGELOG.md as the release notes. When releasing, bump `composer.json` `version` and add a CHANGELOG entry in that exact heading format.

## Architecture

The event logging spans three pieces wired in `src/Resources/config/services.xml`:

1. **`Subscriber/CacheEventsSubscriber`** subscribes to Shopware core cache events (`HttpCacheStoreEvent`, `HttpCacheHitEvent`, `InvalidateCacheEvent`, `HttpCacheKeyEvent`, `InvalidateProductCache`). Each handler reads its own on/off flag from `SystemConfigService` (`DigaShopwareCacheHelper.config.<key>`), wraps everything in try/catch, and logs a pipe-delimited line (`EventName | uri | key | details`). The `HttpCacheKeyEvent` handler rebuilds the cache key the way core does: the SW 6.6+ path (`Feature::isActive('v6.6.0.0')`) hashes `getParts()`; the legacy path uses the hash plus cookies/currency attributes.
2. **`Service/DigaLoggerFactoryService`** is a logger proxy. Depending on config `loggingOutput`, it forwards to the Shopware `monolog.logger` (`shopwarelog`) or to its own `RotatingFileHandler` writing `var/log/diga_cache_helper-<env>.log` (`cachehelperlog`). Log methods are forwarded through `__call`, so it does not implement `LoggerInterface`.
3. **`Resources/config/config.xml`** defines the admin plugin config. Every new logging toggle needs a field here (en + de-DE labels) and a matching `systemConfigService->get()` in the subscriber. The config embeds the custom admin component `diga-info` (`Resources/app/administration/src/component/diga-info`, with snippets in `snippet/de-DE.json` and `en-GB.json`).

### Invalidation origin (`src/Invalidation/`)

This feature logs which plugin caused a cache invalidation and where it was triggered. It is off by default (`logInvalidationOrigin`). It hooks in at two officially extendable points. It deliberately does not decorate `CacheInvalidator`: that class is `@final` by docblock, and PHPStan fails on `class.extendsFinalByPhpDoc`.
- **Deferred path:** `TracingInvalidatorStorage` decorates `AbstractInvalidatorStorage::store()`. It records only after the inner store succeeded, because on a failed store Shopware 6.7.x purges immediately instead.
- **Immediate path:** `Subscriber/InvalidationOriginSubscriber` listens to `InvalidateCacheEvent`. The event is dispatched synchronously inside `CacheInvalidator::purge()`, so the caller is still on the stack.

`InvalidationOriginResolver` anchors on the `CacheInvalidator::invalidate` frame in `debug_backtrace()`. The first frame above it is the trigger. The first frame whose class is in an active plugin's namespace is the plugin; `PluginNamespaceMap` builds that map from `KernelPluginCollection`, longest prefix wins. A `CacheInvalidator::invalidateExpired` frame means the scheduled purge of deferred tags; the resolver returns `null` for it, because the origin was already logged when the tags were stored.

`InvalidationOriginRecorder` reads its settings once per cycle and aggregates entries per request, command or worker message. It writes one `InvalidationOrigin | ...` line per origin on flush. Flush happens on `kernel.terminate`, `console.terminate` and worker message handled/failed; it goes through `reset()`, which is also tagged `kernel.reset`. A shutdown function registered on the first buffered origin is the safety net for cache hits, where Symfony's HttpCache skips `kernel.terminate` and the request stack is empty; the subscriber therefore remembers the request on `kernel.request` and `HttpCacheHitEvent` (`setRequest()`, first main request wins, ESI sub requests come later). The logger is injected as a `service_closure`, so it is only created when something is written.

Shopware 6.7.x has its own optional purge log (`shopware.cache.invalidation.tag_invalidation_log_enabled`). It only shows the direct caller at purge time, which is the scheduled task on the deferred path.

When adapting to a new Shopware version, check that the core cache event classes and their public properties still exist. Past major versions (4.0.x for 6.7, 3.0.0 for 6.6) were mostly about renamed or removed core cache APIs. The README still documents the `diga:http:cache:warmup` command, but it was removed for SW 6.6+ and no longer exists in the code.
