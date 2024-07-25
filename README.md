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

## command to warmup just specific saleschannels
bin/console diga:http:cache:warmup [NavigationRouteWarmer, ProductRouteWarmer, all] [saleschannelId]

example to warmup just "Categories"
bin/console diga:http:cache:warmup NavigationRouteWarmer 018dac09d3b072e9a67f8aa065c4278a

example to warmup everything for a single saleschannel
bin/console diga:http:cache:warmup all 018dac09d3b072e9a67f8aa065c4278a

## Installation manual:
Install and activate

## Supported SW Version:
sw 6.5.x

## Dependencies:
none

## testing 
- e2e testing with playwight see [README.md](tests/e2e/README.md)

## Technical concept:

## License
Copyright 2022 ditegra GmbH
