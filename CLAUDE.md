# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Shopware 6.7 plugin (`diga/shopwarecachehelper-plugin`, namespace `DigaShopwareCacheHelper\`) that logs HTTP cache events (cache hits, item writes with tags, cache key generation, cache/product invalidations) to help debug Shopware caching. It lives inside a Shopware installation at `custom/plugins/DigaShopwareCacheHelper`; the Shopware root is `/var/www/html` (many make targets `cd` there or use `../../../bin/console`).

## Commands

All via the `makefile` (`make help` lists targets):

- `make dev` / `make prod` — composer install with / without dev deps
- `make stan` (alias `make validate`) — PHPStan level 5 on `src/` (excludes `src/Resources`). This is what CI runs.
- `make test` — PHPUnit via `phpunit.xml`. Bootstrap is `../../../src/TestBootstrap.php`, so it must run from inside a Shopware checkout. Tests are expected in `tests/`, which does not currently exist. Single test: `php ./vendor/bin/phpunit --configuration=./phpunit.xml --filter <TestName>`
- `make build` — refresh/install/activate the plugin in the host Shopware, build admin JS (`bin/build-js.sh`), and rewrite the "Supported SW Version" line in README.md with the current SW version
- `make zip` — writes `artifacts/DigaShopwareCacheHelper_<composer version>.zip` (runs `make clean` first; needs `jq`)
- `make release` — clean + build + zip

CI (`.github/workflows/`) runs in `ghcr.io/friendsofshopware/platform-plugin-dev:v6.7.0`: `make dev && make validate`, then `shopware-cli extension build .`, `make zip`, and `shopware-cli extension validate` on the zip. `pr-build-pipe.yml` runs on PRs. `release-pipe.yml` tags `v<composer.json version>` and uses the matching `## [x.y.z]` section of CHANGELOG.md as the release notes. When releasing, bump `composer.json` `version` and add a CHANGELOG entry in that exact heading format.

## Architecture

The plugin is small; the flow spans three pieces wired in `src/Resources/config/services.xml`:

1. **`Subscriber/CacheEventsSubscriber`** subscribes to Shopware core cache events (`HttpCacheStoreEvent`, `HttpCacheHitEvent`, `InvalidateCacheEvent`, `HttpCacheKeyEvent`, `InvalidateProductCache`). Each handler reads its own on/off flag from `SystemConfigService` (`DigaShopwareCacheHelper.config.<key>`), wraps everything in try/catch, and logs a pipe-delimited line (`EventName | uri | key | details`). The `HttpCacheKeyEvent` handler rebuilds the cache key the way core does: the SW 6.6+ path (`Feature::isActive('v6.6.0.0')`) hashes `getParts()`; the legacy path uses the hash plus cookies/currency attributes.
2. **`Service/DigaLoggerFactoryService`** is a logger proxy. Depending on config `loggingOutput`, it forwards to the Shopware `monolog.logger` (`shopwarelog`) or to its own `RotatingFileHandler` writing `var/log/diga_cache_helper-<env>.log` (`cachehelperlog`). Log methods are forwarded through `__call`, so it does not implement `LoggerInterface`.
3. **`Resources/config/config.xml`** defines the admin plugin config. Every new logging toggle needs a field here (en + de-DE labels) and a matching `systemConfigService->get()` in the subscriber. The config embeds the custom admin component `diga-info` (`Resources/app/administration/src/component/diga-info`, with snippets in `snippet/de-DE.json` and `en-GB.json`).

When adapting to a new Shopware version, check that the core cache event classes and their public properties still exist. Past major versions (4.0.x for 6.7, 3.0.0 for 6.6) were mostly about renamed or removed core cache APIs. The README still documents the `diga:http:cache:warmup` command, but it was removed for SW 6.6+ and no longer exists in the code.
