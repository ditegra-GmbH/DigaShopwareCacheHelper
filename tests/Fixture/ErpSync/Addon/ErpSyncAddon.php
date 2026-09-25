<?php

declare(strict_types=1);

namespace DigaTestFixture\ErpSync\Addon;

use Shopware\Core\Framework\Plugin;

/**
 * Plugin living inside the namespace of another plugin, to test that the longest prefix wins.
 */
class ErpSyncAddon extends Plugin
{
}
