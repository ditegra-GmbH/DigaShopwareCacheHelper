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
- writes the logs to default shopware var/log folder
- usefull in production mode

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