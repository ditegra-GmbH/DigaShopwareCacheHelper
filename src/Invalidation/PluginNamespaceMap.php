<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Invalidation;

use Shopware\Core\Framework\Plugin\KernelPluginCollection;

/**
 * Maps a class name to the active plugin whose namespace contains it.
 * Built lazily once per process, lookups are memoized per class.
 */
class PluginNamespaceMap
{
    /**
     * @var array<string, string>|null namespace prefix => plugin name, longest prefix first
     */
    private ?array $prefixes = null;

    /**
     * @var array<string, string|null>
     */
    private array $resolved = [];

    public function __construct(private readonly KernelPluginCollection $plugins)
    {
    }

    public function pluginFor(string $class): ?string
    {
        if (\array_key_exists($class, $this->resolved)) {
            return $this->resolved[$class];
        }

        $this->prefixes ??= $this->buildPrefixes();

        foreach ($this->prefixes as $prefix => $pluginName) {
            if (str_starts_with($class, $prefix)) {
                return $this->resolved[$class] = $pluginName;
            }
        }

        return $this->resolved[$class] = null;
    }

    /**
     * @return array<string, string>
     */
    private function buildPrefixes(): array
    {
        $prefixes = [];

        foreach ($this->plugins->getActives() as $pluginClass => $plugin) {
            $separator = strrpos($pluginClass, '\\');
            if ($separator === false) {
                continue;
            }

            $prefixes[substr($pluginClass, 0, $separator + 1)] = $plugin->getName();
        }

        // longest prefix wins, so nested plugin namespaces resolve to the most specific plugin
        uksort($prefixes, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $prefixes;
    }
}
