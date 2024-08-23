# DigaShopwareCacheHelper
Shopware Cache helper

## Description:
The Cache Helper diligently logs a multitude of events. This not only simplifies the debugging process, but also fosters a deeper understanding of operations occurring during cache writes and invalidations. It provides clear insights into which tags have been written and which ones have been invalidated.

## Highlights:
- Logs multiple CacheTagsEvent(s)
- Logs CacheItemWritten
- Logs CacheHit
- Logs InvalidateCache

## Features:
- writes the logs to the default Shopware var/log folder
- useful in production mode
- log warmed up seo urls (removed in 6.6)
- command to warmup just active storfront saleschannel (removed in 6.6)
- command arguments categories or just a single sales channel (removed in 6.6)
- logs ttl and maxage output on cache hits

## Breaking changes in SW 6.6
Shopware has removed the http cache warmup command and its related messages and handlers.

So we cannot use the diga:http:cache:warmup command anymore.



## Installation manual:
Install and activate

## Supported SW Version:
sw 6.5.x, 6.6.x

## Dependencies:
none

## testing 
- e2e testing with playwight see [README.md](tests/e2e/README.md)

## Technical concept:

## License
Copyright 2024 ditegra GmbH
