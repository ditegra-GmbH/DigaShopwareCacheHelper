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
- log warmed up seo urls
- command to warmup just active storfront saleschannel
- command arguments categories or just a single sales channel
- logs ttl and maxage output on cache hits



## Installation manual:
Install and activate

## Supported SW Version:
sw 6.4.x

## Dependencies:
none

## testing 
- e2e testing with playwight see [README.md](tests/e2e/README.md)

## Technical concept:

## License
Copyright 2022 ditegra GmbH
