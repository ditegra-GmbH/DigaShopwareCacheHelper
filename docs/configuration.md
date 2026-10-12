# Configuration

All settings are in the plugin config (*Extensions > My extensions > Cache Helper > Configure*), stored under `DigaShopwareCacheHelper.config.<name>`. They are global; values per sales channel are not evaluated.

You can also set them from the CLI:

```bash
bin/console system:config:set DigaShopwareCacheHelper.config.logInvalidationOrigin true
```

## Settings

| Name | Label | Default | Effect |
| --- | --- | --- | --- |
| `logFullUri` | Log full url | `true` | Log the full URL with scheme and host instead of only path and query. Applies to all log lines that contain a URI. |
| `logOnCacheHit` | Log onCacheHit | `true` | One `CacheHitEvent` line per request answered from the HTTP cache. |
| `logOnCacheItemWritten` | Log onCacheItemWritten | `true` | One `CacheItemWrittenEvent` line per response written to the HTTP cache. |
| `logTagsOnCacheItemWritten` | Log Tags onCacheItemWritten | `false` | Adds the cache tags to `CacheItemWrittenEvent`. Needs `logOnCacheItemWritten`. |
| `logInvalidateCache` | Log InvalidateCache | `true` | One `InvalidateCacheEvent` line each time Shopware purges tags. |
| `logHttpCacheGenerateKeyEvent` | Log HttpCacheGenerateKeyEvent | `false` | One `HttpCacheGenerateKeyEvent` line per cache key calculation, with the parts the key is built from. |
| `logInvalidateProductCache` | Log InvalidateProductCache | `true` | One `InvalidateProductCache` line when product caches are invalidated. |
| `logInvalidationOrigin` | Log invalidation origin | `false` | One `InvalidationOrigin` line per origin, telling which plugin caused an invalidation and where. See [Invalidation origin](invalidation-origin.md). |
| `invalidationOriginMaxTags` | Max tags per invalidation origin entry | `20` | How many tags an `InvalidationOrigin` line lists. The total count is always logged. |
| `invalidationOriginLogStack` | Log plugin call stack for invalidation origin | `false` | Adds all plugin methods in the call stack to `InvalidationOrigin`, not only the first. |
| `loggingOutput` | Logging output | `shopwarelog` | `shopwarelog`: Shopware's default logger. `cachehelperlog`: separate file of the plugin. See [Log reference](log-reference.md#where-the-logs-go). |

## When changes take effect

- With PHP-FPM, every request reads the settings again, so changes apply to the next request.
- Long-running processes cache some settings. `loggingOutput` is read when the logger is created, and the invalidation origin settings are read once per worker message. Restart workers (`messenger:consume`) and long-running PHP servers after changing `loggingOutput`.

## Performance

- `logOnCacheHit` and `logHttpCacheGenerateKeyEvent` write a line for almost every storefront request. On high traffic this produces large files; enable them only while debugging.
- `logTagsOnCacheItemWritten` can make lines very long, because product pages carry many tags.
- `logInvalidationOrigin` only costs anything while invalidations happen (writes, imports, indexing, admin actions). The storefront cache hit path is not touched. Details: [Invalidation origin > Performance](invalidation-origin.md#performance).
- The plugin's own log file rotates daily and keeps all files. Delete old files yourself, or use `shopwarelog` if your log retention is handled there.
