# Log reference

## Where the logs go

| `loggingOutput` | Target |
| --- | --- |
| `shopwarelog` (default) | Shopware's `monolog.logger`, usually `var/log/<env>.log`. Lines appear with channel `app`, next to all other Shopware messages. |
| `cachehelperlog` | Own file, rotated daily: `var/log/diga_cache_helper-<env>-<YYYY-MM-DD>.log`, channel `file_logger`, level `DEBUG` and above. |

All lines are written with level `INFO`. If a handler fails, the exception message is logged with level `ERROR` and the request continues.

## Line format

Every line has four fields separated by `|`, so it is easy to filter with `grep` or `awk`:

```
<Event> | <URI or context> | <cache key> | <details>
```

Fields that do not apply to an event stay empty.

Filter examples:

```bash
grep 'CacheHitEvent' var/log/diga_cache_helper-prod-*.log
grep 'InvalidationOrigin' var/log/diga_cache_helper-prod-*.log | grep -v '| core |'
awk -F' \\| ' '/CacheItemWrittenEvent/ {print $2}' var/log/diga_cache_helper-prod-*.log | sort | uniq -c | sort -rn | head
```

## Events

### CacheHitEvent

Shopware answered a request from the HTTP cache (`HttpCacheHitEvent`). Setting: `logOnCacheHit`.

```
CacheHitEvent | https://shop.example/men/ | http-cache-5f1c… |  TTL: 7143 maxAge: 7200
```

- **URI**: path and query, or the full URL with `logFullUri`.
- **cache key**: key of the cached item.
- **TTL**: seconds until the cached response expires.
- **maxAge**: `max-age` of the response.

### CacheItemWrittenEvent

A response was stored in the HTTP cache (`HttpCacheStoreEvent`). Setting: `logOnCacheItemWritten`; tags only with `logTagsOnCacheItemWritten`.

```
CacheItemWrittenEvent | https://shop.example/men/ | http-cache-5f1c… |  TTL: 7200 maxAge: 7200 Tags: ["category-route-019808…","product-listing-019808…",…]
```

The tags are what later invalidations match against. If a page is not refreshed after a change, check whether the changed entity's tag is in this list.

### HttpCacheGenerateKeyEvent

Shopware calculated a cache key for a request (`HttpCacheKeyEvent`), both during lookup and before storing. Setting: `logHttpCacheGenerateKeyEvent`.

```
HttpCacheGenerateKeyEvent | https://shop.example/men/ |  |  key http-cache-5f1c… | parts: {"hash":"…","sw-cache-hash":"…","uri":"/men/"}
```

- **key**: `http-cache-` plus the hash of all parts, as Shopware builds it.
- **parts**: everything the key depends on, sorted by name:
  - `uri`: the request URI
  - `hash`: the shop's cache hash
  - one context part: the `sw-cache-hash` cookie (context rules, customer group, …), else the currency cookie or the domain currency

  Other plugins can add parts too. Two requests that should share a cache entry but don't will differ here.

### InvalidateCacheEvent

Shopware purged tags from the caches (`InvalidateCacheEvent`, dispatched in `CacheInvalidator::purge()`). Setting: `logInvalidateCache`.

```
InvalidateCacheEvent |  |  |  keys ["product-019808…","product-listing-route-019808…"]
```

With delayed invalidation (the Shopware default), this line is written by the scheduled task `InvalidateCacheTask` or by `bin/console cache:clear:delayed`, not by the request that made the change. The line does not say who caused the purge; use the [invalidation origin](invalidation-origin.md) for that.

### InvalidateProductCache

Product caches were invalidated (`InvalidateProductCache`). Setting: `logInvalidateProductCache`.

```
InvalidateProductCache |  |  |  ids ["019808ebb4b3…"] force: false
```

- **ids**: product IDs.
- **force**: `true` means the tags are purged immediately instead of delayed.

### InvalidationOrigin

Which plugin caused an invalidation, where it was triggered, and in which context. Setting: `logInvalidationOrigin`. The format and every field are described in [Invalidation origin](invalidation-origin.md#log-line).

```
InvalidationOrigin | cli: system:config:set | core | mode: deferred | calls: 1 | tags: 1 ["system.config-019808e9…"] | trigger: Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateConfigKey
```
