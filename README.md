# DigaShopwareCacheHelper
Shopware Cache helper

## Documentation
See [docs/](docs/README.md): configuration, log reference, invalidation origin, architecture, development.

## Description:
The Cache Helper diligently logs a multitude of events. This not only simplifies the debugging process, but also fosters a deeper understanding of operations occurring during cache writes and invalidations. It provides clear insights into which tags have been written and which ones have been invalidated.

## Highlights:
- Logs multiple CacheTagsEvent(s)
- Logs CacheItemWritten
- Logs CacheHit
- Logs InvalidateCache
- Logs which plugin caused a cache invalidation and where it was triggered

## Features:
- writes the logs to the default Shopware var/log folder
- useful in production mode
- log warmed up seo urls (removed in 6.6)
- command to warmup just active storfront saleschannel (removed in 6.6)
- command arguments categories or just a single sales channel (removed in 6.6)
- logs ttl and maxage output on cache hits

## command to warmup just specific saleschannels
bin/console diga:http:cache:warmup [NavigationRouteWarmer, ProductRouteWarmer, all] [saleschannelId]

example to warmup just "Categories"
bin/console diga:http:cache:warmup NavigationRouteWarmer 018dac09d3b072e9a67f8aa065c4278a

example to warmup everything for a single saleschannel
bin/console diga:http:cache:warmup all 018dac09d3b072e9a67f8aa065c4278a

## Invalidation origin
Enable "Log invalidation origin" in the plugin config (off by default). For each request, CLI command or worker message, the plugin writes one line per origin:

```
InvalidationOrigin | cli: erp:import | ErpSync | mode: deferred | calls: 412 | tags: 824 (first 20) ["product-…", …] | trigger: Vendor\ErpSync\Service\ProductImporter::import | caller: Vendor\ErpSync\Service\ProductImporter::import (ProductImporter.php:88)
```

- **context**: HTTP method + URI, `cli: <command>` or `worker: <message class>`
- **plugin**: first plugin in the call stack; `core` if no plugin is involved
- **mode**: `immediate` (purged right away) or `deferred` (stored, purged later by the scheduled task `InvalidateCacheTask`, which appears as the existing `InvalidateCacheEvent` line; match them via the tags)
- **tags**: total number of invalidated tags over all calls, the list is capped by "Max tags per invalidation origin entry"
- **trigger**: direct caller of `CacheInvalidator::invalidate()`, e.g. `CacheInvalidationSubscriber::invalidateProduct`
- **caller**: plugin method with file and line
- **stack**: all plugin methods in the call stack, only with "Log plugin call stack" enabled

Not covered: invalidations that bypass the `CacheInvalidator` (e.g. direct `invalidateTags()` on a cache pool, `cache:clear`). Apps and API integrations have no PHP code and appear as `core` with the API route as context. For asynchronous indexing, the log shows the worker message instead of the plugin that wrote the data.

## Breaking changes in SW 6.6
Shopware has removed the http cache warmup command and its related messages and handlers.

So we cannot use the diga:http:cache:warmup command anymore.

## Installation manual:
Install and activate

## Supported SW Version:
sw 6.5.x, 6.6.x, 6.7.x

## Dependencies:
none

## testing 
- unit tests: `make dev && make test`

## Technical concept:

## License
Copyright 2024 ditegra GmbH
